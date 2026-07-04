<?php

namespace App\Http\Controllers;

use App\Models\Registry;
use App\Models\Payment;
use App\Models\Income;
use App\Models\Accounts;
use App\Exports\RegistryExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Контроллер управления реестрами платежей
 * 
 * Обеспечивает:
 * - Создание и управление реестрами платежей
 * - Автоматическое заполнение реестра согласованными заявками
 * - Утверждение реестра (только руководитель/админ)
 * - Отправка в банк (только руководитель/админ)
 * - Экспорт реестра
 * 
 * Особенности:
 * - При заполнении учитывается приоритет заявок (high → medium → low)
 * - Проверяется достаточность средств на счете
 * - При недостатке средств выдается предупреждение
 * - Утверждение реестра доступно только руководителю и администратору
 * 
 * Статусы реестра:
 * - draft (Черновик)
 * - approved (Утвержден)
 * - sent_to_bank (Отправлен в банк)
 * - paid (Оплачен)
 */
class RegistryController extends Controller
{
    //Получить список всех реестров с платежами
    public function index()
    {
        return response()->json(Registry::with('payments')->get(), 200);
    }

    //Создать новый реестр
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:draft,sent_to_bank,paid',
        ]);

        return response()->json(Registry::create($validated), 201);
    }

    //Получить реестр по ID с платежами
    public function show(int $id)
    {
        return response()->json(Registry::with('payments')->findOrFail($id), 200);
    }

    //Обновить реестр
    public function update(Request $request, int $id)
    {
        $registry = Registry::findOrFail($id);

        $user = Auth::user();
        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();

        $newStatus = $request->status;

        if (in_array($newStatus, ['sent_to_bank', 'paid'])) {
            if (!in_array('manager', $userRoles) && !in_array('admin', $userRoles)) {
                return response()->json([
                    'error' => 'Только руководитель или администратор может утвердить реестр'
                ], 403);
            }
        }

        $validated = $request->validate([
            'date' => 'nullable|date',
            'status' => 'nullable|in:draft,sent_to_bank,paid',
        ]);

        $registry->update($validated);

        return response()->json($registry, 200);
    }

    //Удалить реестр
    public function destroy(int $id)
    {
        Registry::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    //Заполнить реестр согласованными заявками
    public function attachPayments(int $id)
    {
        $registry = Registry::findOrFail($id);

        $payments = Payment::where('planned_date', $registry->date)
            ->where('status', 'approved')
            ->get();

        $sorted = $payments->sortBy(function ($item) {
            return ['high' => 0, 'medium' => 1, 'low' => 2][$item->priority] ?? 1;
        });

        $updatedCount = 0;
        $totalAmount = 0;
        $warnings = [];

        $firstPayment = $sorted->first();
        $accountBalance = $firstPayment
            ? $this->calculateBalanceOnDate($firstPayment->account_id, $registry->date)
            : 0;

        foreach ($sorted as $payment) {
            $remainingBalance = $accountBalance - $totalAmount;

            if ($remainingBalance < $payment->amount) {
                $warnings[] = "Недостаточно средств для платежа #{$payment->id} ({$payment->name})";
                continue;
            }

            if (!$registry->payments()->where('payment_id', $payment->id)->exists()) {
                $registry->payments()->attach($payment->id);
                $payment->update(['status' => 'in_registry']);
                $totalAmount += $payment->amount;
                $updatedCount++;
            }
        }

        return response()->json([
            'message' => 'Реестр успешно заполнен заявками!',
            'registry_id' => $registry->id,
            'registry_date' => $registry->date,
            'added_count' => $updatedCount,
            'total_amount' => $totalAmount,
            'total_amount_formatted' => number_format($totalAmount / 100, 2, '.', ' ') . ' ₽',
            'warnings' => $warnings,
        ]);
    }

    //Экспорт реестра в Excel
    public function export(int $id)
    {
        return RegistryExport::download($id);
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

    //Утверждение реестра (только руководитель/администратор)
    public function approve(int $id)
    {
        $registry = Registry::findOrFail($id);

        $user = Auth::user();
        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();

        if (!in_array('manager', $userRoles) && !in_array('admin', $userRoles)) {
            return response()->json([
                'error' => 'Только руководитель или администратор может утвердить реестр'
            ], 403);
        }

        if ($registry->status !== 'draft') {
            return response()->json([
                'error' => 'Реестр должен быть в статусе "Черновик"'
            ], 422);
        }

        $registry->status = 'approved';
        $registry->save();

        return response()->json([
            'message' => 'Реестр утвержден',
            'registry' => $registry
        ], 200);
    }

    //Отправка реестра в банк (только руководитель/администратор)
    public function sendToBank(int $id)
    {
        $registry = Registry::findOrFail($id);

        $user = Auth::user();
        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();

        if (!in_array('manager', $userRoles) && !in_array('admin', $userRoles)) {
            return response()->json([
                'error' => 'Только руководитель или администратор может отправить реестр в банк'
            ], 403);
        }

        if ($registry->status !== 'approved') {
            return response()->json([
                'error' => 'Реестр должен быть утвержден'
            ], 422);
        }

        $registry->status = 'sent_to_bank';
        $registry->save();

        return response()->json([
            'message' => 'Реестр отправлен в банк',
            'registry' => $registry
        ], 200);
    }
}
