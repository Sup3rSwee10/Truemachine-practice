<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер аутентификации
 * 
 * Обеспечивает вход, выход и получение информации о текущем пользователе
 * 
 * Особенности:
 * - Rate limiting (ограничение попыток входа) - 10 попыток с IP
 * - Автоматическая блокировка на 60 секунд после превышения
 * - Выдача Sanctum-токена для доступа к API
 */
class AuthController extends Controller
{
    //Вход в систему
    public function login(Request $request)
    {
        $key = 'login.' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error' => "Слишком много попыток входа. Попробуйте через {$seconds} секунд."
            ], 429);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            return response()->json(['error' => 'Неверные учетные данные'], 401);
        }

        RateLimiter::clear($key);

        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Успешный вход',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'roles' => $user->roles()->pluck('name'),
            'token' => $token,
        ]);
    }

    //Выход из системы (удаление текущего токена)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Выход выполнен'
        ]);
    }

    //Получить информацию о текущем пользователе
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'roles' => $user->roles()->pluck('name'),
        ]);
    }
}
