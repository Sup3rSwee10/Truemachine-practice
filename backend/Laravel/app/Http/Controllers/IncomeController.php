<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Item;
use App\Imports\IncomeImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Контроллер управления поступлениями
 * 
 * Обеспечивает:
 * - Создание и редактирование поступлений
 * - Просмотр поступлений (инициатор видит только свои)
 * - Импорт поступлений 
 * - Предварительный просмотр импорта
 * 
 * Особенности:
 * - Для поступлений не используются статусы и приоритеты
 * - Поступления сразу считаются плановыми, без согласования
 * - Инициатор видит только свои поступления
 * - Админ/казначей/руководитель видят все поступления
 */
class IncomeController extends Controller
{

    //Получить список всех поступлений
    public function index()
    {
        $user = Auth::user();
        $query = Income::with(['account', 'counterparty', 'item', 'creator']);

        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();

        if (
            in_array('initiator', $userRoles) &&
            !in_array('admin', $userRoles) &&
            !in_array('treasurer', $userRoles) &&
            !in_array('manager', $userRoles)
        ) {
            $query->where('created_by', $user->id);
        }

        return response()->json($query->get(), 200);
    }

    //Создать новое поступление
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'planned_date' => 'required|date',
            'account_id' => 'required|integer|exists:accounts,id',
            'item_id' => 'required|integer|exists:items,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'is_recurring' => 'boolean',
        ]);

        $item = Item::find($validated['item_id']);
        if (!$item || $item->type !== 'income') {
            return response()->json([
                'error' => 'Для поступления необходимо выбрать статью с типом "income"'
            ], 422);
        }

        $validated['created_by'] = Auth::id();

        return response()->json(Income::create($validated), 201);
    }

    //Получить поступление по ID
    public function show(int $id)
    {
        return response()->json(
            Income::with(['account', 'counterparty', 'item', 'creator'])->findOrFail($id),
            200
        );
    }

    //Обновить поступление
    public function update(Request $request, int $id)
    {
        $income = Income::findOrFail($id);

        $this->authorize('update', $income);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'amount' => 'nullable|integer|min:1',
            'planned_date' => 'nullable|date',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'item_id' => 'nullable|integer|exists:items,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
        ]);

        $income->update($validated);

        return response()->json($income, 200);
    }

    //Удалить поступление
    public function destroy(int $id)
    {
        $income = Income::findOrFail($id);

        $this->authorize('delete', $income);

        $income->delete();

        return response()->json(null, 204);
    }

    //Импорт поступлений
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'account_id' => 'required|integer|exists:accounts,id',
        ]);

        $file = $request->file('file');
        $accountId = $request->account_id;

        $extension = $file->getClientOriginalExtension();
        $fileName = 'import_' . time() . '.' . $extension;
        $filePath = $file->storeAs('imports', $fileName);
        $fullPath = storage_path('app/' . $filePath);

        try {
            $import = new IncomeImport($accountId);
            $import->import($fullPath);
        } catch (\Exception $e) {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            return response()->json([
                'message' => 'Ошибка импорта',
                'error' => $e->getMessage()
            ], 500);
        }

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $response = [
            'message' => 'Импорт поступлений завершен',
            'imported_count' => $import->getCreatedCount(),
        ];

        if ($import->getErrors()) {
            $response['errors'] = $import->getErrors();
            $response['error_count'] = count($import->getErrors());
        }

        return response()->json($response);
    }

    //Предварительный просмотр импорта поступлений
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'account_id' => 'required|integer|exists:accounts,id',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports');
        $fullPath = storage_path('app/' . $path);

        try {
            $rows = SimpleExcelReader::create($fullPath)->getRows();

            $preview = [];
            $totalRows = 0;

            foreach ($rows as $index => $row) {
                $totalRows++;
                if ($index < 10) {
                    $preview[] = [
                        'row_number' => $index + 1,
                        'data' => $row,
                        'valid' => $this->validateImportRow($row),
                    ];
                }
            }

            Storage::delete($path);

            return response()->json([
                'total_rows' => $totalRows,
                'preview_rows' => count($preview),
                'preview' => $preview,
                'columns' => $preview ? array_keys($preview[0]['data']) : [],
            ], 200);
        } catch (\Exception $e) {
            Storage::delete($path);
            return response()->json([
                'error' => 'Ошибка чтения файла: ' . $e->getMessage()
            ], 422);
        }
    }

    //Валидация строки для импорта поступлений
    private function validateImportRow(array $row): array
    {
        $errors = [];

        if (empty($row['контрагент'])) {
            $errors[] = 'Не указан контрагент';
        }
        if (empty($row['сумма']) || floatval($row['сумма']) <= 0) {
            $errors[] = 'Сумма должна быть больше 0';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
