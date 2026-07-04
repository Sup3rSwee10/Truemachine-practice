<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

/**
 * Контроллер управления статьями движения денег
 * 
 * Обеспечивает:
 * - Просмотр списка всех статей
 * - Создание новых статей с указанием типа (доход/расход)
 * 
 * Статьи используются для классификации:
 * - Платежей (тип 'expense')
 * - Поступлений (тип 'income')
 * 
 * Доступ:
 * - Просмотр: все авторизованные пользователи
 * - Создание: только администратор
 */
class ItemController extends Controller
{
    //Получить список всех статей
    public function index()
    {
        return response()->json(Item::all(), 200);
    }

    //Создать новую статью
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
        ]);

        $item = Item::create($validated);

        return response()->json($item, 201);
    }
}
