<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Observer для модели Payment
 * 
 * Автоматически логирует все изменения в платежах:
 * - Создание платежа
 * - Обновление полей платежа
 * - Изменение статуса при редактировании
 */
class PaymentObserver
{

    //Событие: создание нового платежа
    public function created(Payment $payment): void
    {
        AuditLog::create([
            'user_id' => Auth::id() ?? $payment->created_by ?? 1,
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'action' => 'create',
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
        ]);
    }

    //Событие: обновление платежа
    public function updated(Payment $payment): void
    {
        $changedFields = $payment->getChanges();

        foreach ($changedFields as $field => $newValue) {
            if (in_array($field, ['updated_at', 'created_at'])) {
                continue;
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'action' => 'update',
                'field_name' => $field,
                'old_value' => $payment->getOriginal($field),
                'new_value' => $newValue,
            ]);
        }
    }

    /**
     * Событие: перед обновлением платежа
     * 
     * Автоматически сбрасывает статус платежа, если изменяются ключевые поля
     * 
     * Правила:
     * - Если изменяются: amount, planned_date, account_id, item_id
     * - И старый статус был: approved, approved_moved, in_registry, paid
     * - То новый статус становится: under_approval (требуется повторное согласование)
     */
    public function updating(Payment $payment): void
    {
        if ($payment->isDirty(['amount', 'planned_date', 'account_id', 'item_id'])) {
            if (in_array($payment->getOriginal('status'), ['approved', 'approved_moved', 'in_registry', 'paid'])) {
                $payment->status = 'under_approval';
            }
        }
    }
}
