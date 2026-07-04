<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;

/**
 * Политика доступа для модели Payment 
 * 
 * Определяет, какие действия может выполнять пользователь с платежами
 * 
 * Доступ по ролям:
 * - Администратор (admin): полный доступ ко всем платежам
 * - Казначей (treasurer): просмотр всех, редактирование всех, согласование, перенос, отметка об оплате
 * - Руководитель (manager): просмотр всех, согласование, отметка об оплате
 * - Инициатор (initiator): просмотр/редактирование/удаление только своих платежей
 */
class PaymentPolicy
{
    //Проверка: может ли пользователь просматривать список всех платежей
    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'treasurer', 'manager'])) {
            return true;
        }

        if ($user->hasRole('initiator')) {
            return true;
        }

        return false;
    }

    //Проверка: может ли пользователь просматривать конкретный платеж
    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasAnyRole(['treasurer', 'manager'])) {
            return true;
        }

        if ($user->hasRole('initiator')) {
            return $user->id === $payment->created_by;
        }

        return false;
    }

    //Проверка: может ли пользователь создавать платежи
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['initiator', 'treasurer', 'manager', 'admin']);
    }

    //Проверка: может ли пользователь редактировать платеж
    public function update(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('treasurer')) {
            return true;
        }

        if ($user->hasRole('initiator')) {
            return $user->id === $payment->created_by;
        }

        return false;
    }

    //Проверка: может ли пользователь удалять платеж
    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasRole('admin');
    }

    //Проверка: может ли пользователь согласовывать (утверждать) платеж
    public function approve(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['treasurer', 'manager', 'admin']);
    }

    //Проверка: может ли пользователь переносить платеж
    public function reschedule(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['treasurer', 'admin']);
    }

    //Проверка: может ли пользователь отмечать платеж как оплаченный
    public function markAsPaid(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['treasurer', 'manager', 'admin']);
    }
}
