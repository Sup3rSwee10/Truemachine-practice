<?php

namespace App\Services;

use App\Models\Accounts;
use App\Models\Payment;
use App\Models\Income;

/**
 * Сервис для расчета баланса на счете
 * 
 * Вычисляет остаток на счете на конкретную дату с учетом:
 * - Начального баланса счета
 * - Всех поступлений до указанной даты
 * - Всех платежей до указанной даты (только определенные статусы)

 */
class BalanceService
{
    /**
     * Рассчитать баланс на счете на конкретную дату
     * 
     * Формула:
     * Баланс = Начальный баланс + Поступления - Платежи
     * 
     * Учитываются платежи со статусами:
     * - approved (Согласована)
     * - approved_moved (Согласована с переносом)
     * - in_registry (В реестре)
     * - paid (Оплачена)
     * - under_approval (На согласовании)
     */
    public function getBalanceOnDate(int $accountId, string $date): int
    {
        $account = Accounts::findOrFail($accountId);
        $balance = $account->initial_balance;

        $totalIncomes = Income::where('account_id', $accountId)
            ->where('planned_date', '<=', $date)
            ->sum('amount');

        $totalPayments = Payment::where('account_id', $accountId)
            ->whereIn('status', ['approved', 'approved_moved', 'in_registry', 'paid', 'under_approval'])
            ->where('planned_date', '<=', $date)
            ->sum('amount');

        return $balance + $totalIncomes - $totalPayments;
    }
}
