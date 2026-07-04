<?php

namespace App\Http\Controllers;

use App\Models\Accounts;
use Illuminate\Http\Request;

/**
 * Контроллер управления счетами
 * 
 * Обеспечивает CRUD-операции для банковских счетов и касс
 */
class AccountsController extends Controller
{
    //Получить список всех счетов
    public function index()
    {
        return response()->json(Accounts::all(), 200);
    }

    //Создать новый счет
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'required|string|unique:accounts,account_number',
            'bank_name' => 'required|string|max:255',
            'bik' => 'required|string|size:9',
            'correspondent_account' => 'required|string|max:20',
            'initial_balance' => 'required|integer|min:0',
            'currency_id' => 'required|integer|exists:currencies,id',
        ]);

        $account = Accounts::create($validated);

        return response()->json($account, 201);
    }

    //Получить счет по ID
    public function show(int $id)
    {
        $account = Accounts::findOrFail($id);

        return response()->json($account, 200);
    }

    //Обновить счет
    public function update(Request $request, int $id)
    {
        $account = Accounts::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|unique:accounts,account_number,' . $id,
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|size:9',
            'correspondent_account' => 'nullable|string|max:20',
            'initial_balance' => 'sometimes|required|integer|min:0',
            'currency_id' => 'sometimes|required|integer|exists:currencies,id',
        ]);

        $account->update($validated);

        return response()->json($account, 200);
    }

    //Удалить счет
    public function destroy(int $id)
    {
        $account = Accounts::findOrFail($id);
        $account->delete();

        return response()->json(null, 204);
    }
}
