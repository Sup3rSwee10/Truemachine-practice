<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Middleware для проверки ролей пользователя
 * 
 * Проверяет, имеет ли авторизованный пользователь одну из требуемых ролей.
 * Если пользователь не авторизован или не имеет нужной роли - доступ запрещен.
 */
class CheckRole
{
    //Обработка входящего запроса
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return response()->json([
                'error' => 'Необходимо авторизоваться'
            ], 401);
        }

        $userId = Auth::id();

        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->pluck('roles.name')
            ->toArray();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Недостаточно прав'
        ], 403);
    }
}
