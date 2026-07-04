<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Payment;

/**
 * Сервис для работы с согласованиями платежей
 * 
 * Обеспечивает:
 * - Принятие решения по заявке (утверждение/отклонение)
 * - Получение истории согласований
 * - Проверку наличия решений
 * - Получение последнего решения
 */
class ApprovalService
{
    /**
     * Принять решение по заявке
     * 
     * Создает запись в журнале согласований и обновляет статус заявки
     */
    public function makeDecision(int $paymentId, ?int $userId, string $decision, ?string $comment = null): Approval
    {
        $payment = Payment::findOrFail($paymentId);

        $approval = Approval::create([
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'decision' => $decision,
            'comment' => $comment,
        ]);

        $payment->update([
            'status' => $decision
        ]);

        return $approval;
    }

    /**
     * Получить историю согласований по платежу
     * 
     * Возвращает все записи согласований для указанного платежа,
     * отсортированные от новых к старым
     */
    public function getApprovalsForPayment(int $paymentId)
    {
        return Approval::where('payment_id', $paymentId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    //Проверить, было ли решение по платежу
    public function hasDecision(int $paymentId): bool
    {
        return Approval::where('payment_id', $paymentId)->exists();
    }

    //Получить последнее решение по платежу
    public function getLatestDecision(int $paymentId): ?Approval
    {
        return Approval::where('payment_id', $paymentId)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
