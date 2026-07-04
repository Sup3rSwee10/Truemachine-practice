<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Контроллер управления пользователями (только для администратора)
 * 
 * Обеспечивает полный CRUD для пользователей системы:
 * - Создание пользователей с назначением ролей
 * - Просмотр и редактирование профилей
 * - Удаление пользователей 
 * - Сброс паролей
 */
class UserController extends Controller
{
    //Получить список всех пользователей с их ролями
    public function index()
    {
        $users = User::with('roles')->get();

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            })
        ], 200);
    }

    //Создание нового пользователя
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'roles' => 'required|array',
            'roles.*' => 'in:initiator,treasurer,manager,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
            $user->roles()->attach($roleIds);

            DB::commit();

            return response()->json([
                'message' => 'Пользователь создан',
                'user' => $user->load('roles'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Ошибка создания пользователя: ' . $e->getMessage()
            ], 500);
        }
    }

    //Просмотр одного пользователя по ID
    public function show(int $id)
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 200);
    }

    //Обновление данных пользователя
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'roles' => 'nullable|array',
            'roles.*' => 'in:initiator,treasurer,manager,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $data = [];

            if ($request->has('name')) {
                $data['name'] = $request->name;
            }

            if ($request->has('email')) {
                $data['email'] = $request->email;
            }

            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            if ($request->has('roles')) {
                $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
                $user->roles()->sync($roleIds);
            }

            DB::commit();

            return response()->json([
                'message' => 'Пользователь обновлён',
                'user' => $user->load('roles'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Ошибка обновления: ' . $e->getMessage()
            ], 500);
        }
    }

    //Удаление пользователя
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json([
                'error' => 'Нельзя удалить самого себя'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user->roles()->detach();

            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'Пользователь удалён'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Ошибка удаления: ' . $e->getMessage()
            ], 500);
        }
    }

    //Сброс пароля пользователя 
    public function resetPassword(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Пароль успешно сброшен',
        ], 200);
    }
}
