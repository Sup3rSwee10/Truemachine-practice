<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Income;
use App\Models\Accounts;
use App\Models\ExchangeRate;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\ReportExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Контроллер управления финансовыми отчетами
 * 
 * Обеспечивает формирование и выгрузку следующих отчетов:
 * - Остатки по счетам на дату (с конвертацией валют)
 * - Кассовые разрывы за период
 * - План-факт по поступлениям и платежам
 * 
 * Особенности:
 * - Все отчеты можно экспортировать
 * - Отчеты сохраняются в БД для истории
 * - Доступно скачивание ранее сохраненных отчетов
 * 
 * Доступ: казначей, руководитель, администратор
 */
class ReportController extends Controller
{
    /**
     * Отчет по остаткам на счетах на указанную дату
     * 
     * Возвращает баланс по каждому счету в валюте счета
     * и общий баланс в рублях (с конвертацией)
     */
    public function balances(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $date = Carbon::parse($request->date);
        $accounts = Accounts::with('currency')->get();

        $result = [];
        $totalRub = 0;

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
                }
            }
            $totalRub += $balanceRub;

            $result[] = [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'currency' => $account->currency?->code ?? 'RUB',
                'balance' => $balance,
                'balance_formatted' => number_format($balance / 100, 2, '.', ' ') . ' ' . ($account->currency?->symbol ?? '₽'),
                'balance_rub' => $balanceRub,
                'balance_rub_formatted' => number_format($balanceRub / 100, 2, '.', ' ') . ' ₽',
            ];
        }

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile);

            $writer->addRow(['Счет', 'Валюта', 'Баланс (коп.)', 'Баланс (₽)']);
            foreach ($result as $account) {
                $writer->addRow([
                    $account['account_name'],
                    $account['currency'],
                    $account['balance'],
                    $account['balance_formatted'],
                ]);
            }

            $excelContent = file_get_contents($tempFile);
            unlink($tempFile);

            $this->saveReport(
                'Балансы на ' . $date->format('d.m.Y'),
                'balances',
                $excelContent,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ['date' => $date->format('Y-m-d')]
            );
        } catch (\Exception $e) {
            Log::error('Ошибка сохранения отчета: ' . $e->getMessage());
        }

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'total_balance_rub' => $totalRub,
            'total_balance_rub_formatted' => number_format($totalRub / 100, 2, '.', ' ') . ' ₽',
            'accounts' => $result
        ]);
    }

    //Отчет по кассовым разрывам за период
    public function cashGapsReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $calendarController = new CalendarController();
        $calendarRequest = new Request([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        $result = $calendarController->getCashGaps($calendarRequest);
        $data = $result->getData();

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile);

            $writer->addRow(['ID счета', 'Название счета', 'Дата', 'Баланс (коп.)', 'Дефицит (коп.)']);
            foreach ($data->cash_gaps as $gap) {
                $writer->addRow([
                    $gap->account_id,
                    $gap->account_name,
                    $gap->date,
                    $gap->balance_end,
                    $gap->deficit,
                ]);
            }

            $excelContent = file_get_contents($tempFile);
            unlink($tempFile);

            $this->saveReport(
                'Кассовые разрывы с ' . $request->start_date . ' по ' . $request->end_date,
                'cash_gaps',
                $excelContent,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date
                ]
            );
        } catch (\Exception $e) {
            Log::error('Ошибка сохранения отчета: ' . $e->getMessage());
        }

        return response()->json([
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date
            ],
            'total_gaps' => $data->total_gaps ?? 0,
            'cash_gaps' => $data->cash_gaps ?? [],
        ]);
    }

    //Отчет План-Факт за период
    public function planFact(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'account_id' => 'nullable|integer|exists:accounts,id',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $incomeQuery = Income::whereBetween('planned_date', [$startDate, $endDate]);
        $paymentQuery = Payment::whereBetween('planned_date', [$startDate, $endDate]);

        if ($request->account_id) {
            $incomeQuery->where('account_id', $request->account_id);
            $paymentQuery->where('account_id', $request->account_id);
        }

        $planIncomes = (clone $incomeQuery)->sum('amount');
        $planPayments = (clone $paymentQuery)->sum('amount');

        $factIncomes = (clone $incomeQuery)->sum('amount');
        $factPayments = (clone $paymentQuery)->where('status', 'paid')->sum('amount');

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'incomes' => [
                'plan' => $planIncomes,
                'plan_formatted' => number_format($planIncomes / 100, 2, '.', ' ') . ' ₽',
                'fact' => $factIncomes,
                'fact_formatted' => number_format($factIncomes / 100, 2, '.', ' ') . ' ₽',
                'execution_percent' => $planIncomes > 0 ? round(($factIncomes / $planIncomes) * 100, 1) : 0,
            ],
            'payments' => [
                'plan' => $planPayments,
                'plan_formatted' => number_format($planPayments / 100, 2, '.', ' ') . ' ₽',
                'fact' => $factPayments,
                'fact_formatted' => number_format($factPayments / 100, 2, '.', ' ') . ' ₽',
                'execution_percent' => $planPayments > 0 ? round(($factPayments / $planPayments) * 100, 1) : 0,
            ],
        ];

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile);

            $writer->addRow(['Отчет План-Факт']);
            $writer->addRow(['Период:', $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d')]);
            $writer->addRow([]);

            $writer->addRow(['ПОСТУПЛЕНИЯ']);
            $writer->addRow(['Показатель', 'Сумма']);
            $writer->addRow(['План', $data['incomes']['plan_formatted']]);
            $writer->addRow(['Факт', $data['incomes']['fact_formatted']]);
            $writer->addRow(['Исполнение, %', $data['incomes']['execution_percent'] . '%']);
            $writer->addRow([]);

            $writer->addRow(['ПЛАТЕЖИ']);
            $writer->addRow(['Показатель', 'Сумма']);
            $writer->addRow(['План', $data['payments']['plan_formatted']]);
            $writer->addRow(['Факт', $data['payments']['fact_formatted']]);
            $writer->addRow(['Исполнение, %', $data['payments']['execution_percent'] . '%']);
            $writer->addRow([]);

            $writer->addRow(['БАЛАНС']);
            $writer->addRow(['План', $planIncomes - $planPayments]);
            $writer->addRow(['Факт', $factIncomes - $factPayments]);

            $excelContent = file_get_contents($tempFile);
            unlink($tempFile);

            $this->saveReport(
                'План-Факт с ' . $request->start_date . ' по ' . $request->end_date,
                'plan_fact',
                $excelContent,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'account_id' => $request->account_id
                ]
            );
        } catch (\Exception $e) {
            Log::error('Ошибка сохранения отчета: ' . $e->getMessage());
        }

        return response()->json($data);
    }

    //Рассчитать баланс на счете на конкретную дату
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

    //Экспорт отчета по балансам
    public function exportBalances(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $data = $this->balances($request)->getData();
        $date = $request->date;

        return ReportExport::balances($data, $date);
    }

    //Экспорт отчета по кассовым разрывам
    public function exportCashGaps(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->cashGapsReport($request)->getData();
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        return ReportExport::cashGaps($data, $startDate, $endDate);
    }

    //Экспорт отчета План-Факт
    public function exportPlanFact(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->planFact($request)->getData();
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        return ReportExport::planFact($data, $startDate, $endDate);
    }

    //Сохранить отчет в БД
    private function saveReport(string $name, string $type, string $fileContent, string $mimeType, array $parameters = [])
    {
        return Report::create([
            'name' => $name,
            'type' => $type,
            'generated_by' => Auth::id(),
            'parameters' => $parameters,
            'mime_type' => $mimeType,
            'file_content' => $fileContent,
        ]);
    }

    //Получить историю сохраненных отчетов
    public function history(Request $request)
    {
        $query = Report::with('generator');

        if ($request->type) {
            $query->where('type', $request->type);
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(20),
            200
        );
    }

    //Скачать сохраненный отчет
    public function download(int $id)
    {
        $report = Report::findOrFail($id);

        $content = $report->file_content;

        if (is_resource($content)) {
            $content = stream_get_contents($content);
        }

        return response($content)
            ->header('Content-Type', $report->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . $report->name . '.' . $report->extension . '"');
    }
}
