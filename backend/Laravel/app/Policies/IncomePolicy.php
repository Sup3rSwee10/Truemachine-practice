<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Income;

/**
 * Политика доступа для модели Income 
 * 
 * Определяет, какие действия может выполнять пользователь с поступлениями
 * 
 * Доступ:
 * - Все авторизованные пользователи могут просматривать поступления
 * - Создание: все роли (инициатор, казначей, руководитель, админ)
 * - Редактирование: админ, казначей, руководитель (все), инициатор (только свои)
 * - Удаление: админ (все), инициатор (только свои)
 */
class IncomePolicy
{
    //Проверка: может ли пользователь просматривать список поступлений
    public function viewAny(User $user): bool
    {
        return true;
    }

    //Проверка: может ли пользователь просматривать конкретное поступление
    public function view(User $user, Income $income): bool
    {
        return true;
    }

    //Проверка: может ли пользователь создавать поступления
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['initiator', 'treasurer', 'manager', 'admin']);
    }

    //Проверка: может ли пользователь редактировать поступление
    public function update(User $user, Income $income): bool
    {
        if ($user->hasAnyRole(['admin', 'treasurer', 'manager'])) {
            return true;
        }

        if ($user->hasRole('initiator')) {
            return $user->id === $income->created_by;
        }

        return false;
    }

    //Проверка: может ли пользователь удалять поступление
    public function delete(User $user, Income $income): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('initiator')) {
            return $user->id === $income->created_by;
        }

        return false;
    }
}
