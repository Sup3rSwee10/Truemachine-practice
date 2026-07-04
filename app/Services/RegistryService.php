<?php

namespace App\Services;

use App\Models\Registry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Сервис для работы с реестрами платежей
 * 
 * Обеспечивает автоматическое создание реестров на основе согласованных платежей
 * 
 * Логика работы:
 * 1. Находит все согласованные платежи на указанную дату
 * 2. Создает реестр со статусом 'draft'
 * 3. Добавляет все найденные платежи в реестр
 * 4. Обновляет статус платежей на 'in_registry'
 */
class RegistryService
{
    /**
     * Создать реестр для указанной даты
     * 
     * Автоматически находит все согласованные платежи на эту дату
     * и добавляет их в новый реестр
     */
    public function createRegistryForDate(string $date): ?Registry
    {
        return DB::transaction(function () use ($date) {
            $payments = Payment::where('planned_date', $date)
                ->where('status', 'approved')
                ->get();

            if ($payments->isEmpty()) {
                return null;
            }

            $registry = Registry::create([
                'date' => $date,
                'status' => 'draft'
            ]);

            foreach ($payments as $payment) {
                $registry->payments()->attach($payment->id);

                $payment->update(['status' => 'in_registry']);
            }

            return $registry;
        });
    }
}
