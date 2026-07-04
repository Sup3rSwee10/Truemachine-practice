<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Income;
use App\Models\Accounts;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Контроллер платёжного календаря
 * 
 * Отвечает за отображение и расчёт:
 * - Календаря платежей и поступлений по дням
 * - Остатков на счетах на конец каждого дня
 * - Кассовых разрывов
 * - Общего баланса с конвертацией валют
 * 
 * Все расчёты производятся в реальном времени на основе данных в БД
 */
class CalendarController extends Controller
{
    /**
     * Получить платёжный календарь за выбранный период
     * 
     * Возвращает детальную информацию по каждому счету:
     * - Ежедневные поступления и платежи
     * - Остаток на начало и конец дня
     * - Признак кассового разрыва
     * - Список операций за день
     */
    public function getCalendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'item_id' => 'nullable|integer|exists:items,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'status' => 'nullable|in:draft,under_approval,approved,approved_moved,in_registry,paid,rejected',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $accountId = $request->account_id;
        $itemId = $request->item_id;
        $counterpartyId = $request->counterparty_id;
        $status = $request->status;

        $accounts = $accountId
            ? Accounts::where('id', $accountId)->get()
            : Accounts::all();

        $result = [];

        foreach ($accounts as $account) {
            $calendarData = $this->calculateCalendarForAccount(
                $account,
                $startDate,
                $endDate,
                $itemId,
                $counterpartyId,
                $status
            );
            $result[] = ['account' => $account, 'days' => $calendarData];
        }

        return response()->json([
            'period' => ['start' => $startDate->format('Y-m-d'), 'end' => $endDate->format('Y-m-d')],
            'accounts' => $result,
        ]);
    }

    /**
     * Рассчитать календарь для одного счета
     * 
     * Проходит по каждому дню в периоде и вычисляет:
     * - Сумму поступлений за день
     * - Сумму платежей за день
     * - Остаток на конец дня
     * - Наличие кассового разрыва
     */
    private function calculateCalendarForAccount($account, $startDate, $endDate, $itemId = null, $counterpartyId = null, $status = null)
    {
        $days = [];
        $currentDate = $startDate->copy();

        $prevDate = $startDate->copy()->subDay();
        $balance = $this->calculateBalanceOnDate($account->id, $prevDate->format('Y-m-d'));

        $paymentQuery = Payment::where('account_id', $account->id)
            ->whereBetween('planned_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'approved_moved', 'in_registry', 'paid', 'under_approval']);

        if ($itemId) $paymentQuery->where('item_id', $itemId);
        if ($counterpartyId) $paymentQuery->where('counterparty_id', $counterpartyId);
        if ($status) $paymentQuery->where('status', $status);

        $incomeQuery = Income::where('account_id', $account->id)
            ->whereBetween('planned_date', [$startDate, $endDate]);

        if ($itemId) $incomeQuery->where('item_id', $itemId);
        if ($counterpartyId) $incomeQuery->where('counterparty_id', $counterpartyId);

        $paymentGroups = $paymentQuery->get()->groupBy('planned_date');
        $incomeGroups = $incomeQuery->get()->groupBy('planned_date');

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = [
                'date' => $dateStr,
                'incomes' => 0,
                'payments' => 0,
                'balance_start' => $balance,
                'balance_end' => 0,
                'is_cash_gap' => false,
                'items' => []
            ];

            if (isset($incomeGroups[$dateStr])) {
                foreach ($incomeGroups[$dateStr] as $inc) {
                    $dayData['incomes'] += $inc->amount;
                    $dayData['items'][] = [
                        'id' => $inc->id,
                        'type' => 'income',
                        'amount' => $inc->amount,
                        'description' => $inc->description,
                        'status' => null,
                        'counterparty' => $inc->counterparty?->name,
                    ];
                }
            }

            if (isset($paymentGroups[$dateStr])) {
                foreach ($paymentGroups[$dateStr] as $pay) {
                    $dayData['payments'] += $pay->amount;
                    $dayData['items'][] = [
                        'id' => $pay->id,
                        'type' => 'payment',
                        'amount' => $pay->amount,
                        'description' => $pay->description,
                        'status' => $pay->status,
                        'priority' => $pay->priority,
                        'counterparty' => $pay->counterparty?->name,
                    ];
                }
            }

            $dayData['balance_end'] = $balance + $dayData['incomes'] - $dayData['payments'];
            $dayData['is_cash_gap'] = $dayData['balance_end'] < 0;

            $balance = $dayData['balance_end'];
            $days[] = $dayData;
            $currentDate->addDay();
        }

        return $days;
    }

    //Получить список кассовых разрывов за период
    public function getCashGaps(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $calendarData = $this->getCalendar($request);
        $data = $calendarData->getData();

        $gaps = [];

        foreach ($data->accounts as $accountData) {
            foreach ($accountData->days as $day) {
                if ($day->is_cash_gap) {
                    $gaps[] = [
                        'account_id' => $accountData->account->id,
                        'account_name' => $accountData->account->name,
                        'date' => $day->date,
                        'balance_end' => $day->balance_end,
                        'deficit' => abs($day->balance_end),
                    ];
                }
            }
        }

        return response()->json([
            'cash_gaps' => $gaps,
            'total_gaps' => count($gaps)
        ]);
    }

    /**
     * Получить общий баланс по всем счетам с разделением по валютам
     * 
     * Возвращает:
     * - Баланс по каждому счету в валюте счета
     * - Суммарный баланс по каждой валюте
     * - Общий баланс в рублях (с конвертацией)
     */
    public function getTotalBalance(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $date = Carbon::parse($request->date);
        $accounts = Accounts::with('currency')->get();

        $totalBalanceRub = 0;
        $details = [];
        $balancesByCurrency = [];

        foreach ($accounts as $account) {
            $balance = $this->calculateBalanceOnDate($account->id, $date);

            $balanceRub = $balance;
            if ($account->currency && $account->currency->code !== 'RUB') {
                $rate = ExchangeRate::where('currency_id', $account->currency_id)
                    ->where('rate_date', '<=', $date)
                    ->orderBy('rate_date', 'desc')
                    ->first();

                if ($rate) {
                    $balanceRub = (int) round($balance * $rate->rate_to_rub);
                } else {
                    $balanceRub = 0;
                }
            }

            $totalBalanceRub += $balanceRub;

            $currencyCode = $account->currency?->code ?? 'RUB';
            if (!isset($balancesByCurrency[$currencyCode])) {
                $balancesByCurrency[$currencyCode] = 0;
            }
            $balancesByCurrency[$currencyCode] += $balance;

            $details[] = [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'currency' => $currencyCode,
                'balance' => $balance,
                'balance_formatted' => number_format($balance / 100, 2, '.', ' ') . ' ' . ($account->currency?->symbol ?? '₽'),
            ];
        }

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'total_balance_rub' => $totalBalanceRub,
            'total_balance_rub_formatted' => number_format($totalBalanceRub / 100, 2, '.', ' ') . ' ₽',
            'balances_by_currency' => $balancesByCurrency,
            'details' => $details,
        ]);
    }

    /**
     * Рассчитать остаток на счете на конкретную дату
     * 
     * Учитывает все поступления и платежи (с определенными статусами)
     * до указанной даты включительно
     */
    private function calculateBalanceOnDate(int $accountId, string $date): int
    {
        $account = Accounts::findOrFail($accountId);
        $balance = $account->initial_balance;

        $incomes = Income::where('account_id', $accountId)
            ->where('planned_date', '<=', $date)
            ->sum('amount');

        $payments = Payment::where('account_id', $accountId)
            ->where('planned_date', '<=', $date)
            ->whereIn('status', ['approved', 'approved_moved', 'in_registry', 'paid', 'under_approval'])
            ->sum('amount');

        return $balance + $incomes - $payments;
    }
}
