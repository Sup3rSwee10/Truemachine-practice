<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Payment;
use App\Models\Income;
use Illuminate\Http\Request;

/**
 * Контроллер управления статьями движения денег
 * 
 * Обеспечивает:
 * - Просмотр списка всех статей
 * - Просмотр конкретной статьи
 * - Создание новых статей с указанием типа (доход/расход)
 * - Обновление статей
 * - Удаление статей (с проверкой использования)
 * 
 * Статьи используются для классификации:
 * - Платежей (тип 'expense')
 * - Поступлений (тип 'income')
 * 
 * Доступ:
 * - Просмотр: все авторизованные пользователи
 * - Создание/обновление/удаление: только администратор
 */
class ItemController extends Controller
{
    /**
     * Получить список всех статей
     * Доступ: все авторизованные пользователи
     */
    public function index()
    {
        return response()->json(Item::all(), 200);
    }

    /**
     * Получить статью по ID
     * Доступ: все авторизованные пользователи
     */
    public function show($id)
    {
        $item = Item::findOrFail($id);
        return response()->json($item, 200);
    }

    /**
     * Создать новую статью
     * Доступ: только администратор
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
        ]);

        $item = Item::create($validated);

        return response()->json([
            'message' => 'Статья создана',
            'item' => $item
        ], 201);
    }

    /**
     * Обновить статью
     * Доступ: только администратор
     */
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:income,expense',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Статья обновлена',
            'item' => $item
        ], 200);
    }

    /**
     * Удалить статью
     * Доступ: только администратор
     * Проверяет, используется ли статья в платежах или поступлениях.
     * Если используется - удаление запрещено.
     */
    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        $usedInPayments = Payment::where('item_id', $id)->exists();
        $usedInIncomes = Income::where('item_id', $id)->exists();

        if ($usedInPayments || $usedInIncomes) {
            return response()->json([
                'error' => 'Нельзя удалить статью, так как она используется в платежах или поступлениях'
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Статья удалена'
        ], 200);
    }
}
