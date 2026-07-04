<?php

namespace App\Http\Controllers;

use App\Models\Counterparty;
use Illuminate\Http\Request;

/**
 * Контроллер управления контрагентами
 * 
 * Обеспечивает CRUD-операции для контрагентов:
 * - Просмотр списка всех контрагентов
 * - Создание нового контрагента с реквизитами
 * - Обновление данных контрагента
 * - Просмотр конкретного контрагента
 * - Удаление контрагента
 * 
 * Доступ:
 * - Просмотр: все авторизованные пользователи
 * - Создание/Обновление/Удаление: только администратор
 */
class CounterpartyController extends Controller
{
    //Получить список всех контрагентов
    public function index()
    {
        return response()->json(Counterparty::all(), 200);
    }

    //Создать нового контрагента
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'inn' => 'required|string|max:20',
            'bank_name' => 'required|string|max:255',
            'bik' => 'required|string|size:9',
            'correspondent_account' => 'required|string|max:20',
            'current_account' => 'required|string|max:20',
        ]);

        $counterparty = Counterparty::create($validated);

        return response()->json($counterparty, 201);
    }

    //Обновить данные контрагента
    public function update(Request $request, int $id)
    {
        $counterparty = Counterparty::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'inn' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|size:9',
            'correspondent_account' => 'nullable|string|max:20',
            'current_account' => 'nullable|string|max:20',
        ]);

        $counterparty->update($validated);

        return response()->json($counterparty, 200);
    }

    //Получить контрагента по ID
    public function show(int $id)
    {
        $counterparty = Counterparty::findOrFail($id);

        return response()->json($counterparty, 200);
    }

    //Удалить контрагента
    public function destroy(int $id)
    {
        $counterparty = Counterparty::findOrFail($id);
        $counterparty->delete();

        return response()->json(null, 204);
    }
}
