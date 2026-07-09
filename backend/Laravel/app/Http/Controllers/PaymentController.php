<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Accounts;
use App\Models\Income;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Imports\PaymentImport;
use App\Services\CurrencyService;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Контроллер управления платежами
 * 
 * Обеспечивает полный жизненный цикл платежей:
 * - Создание и редактирование заявок
 * - Отправка на согласование
 * - Согласование/отклонение
 * - Перенос на другую дату или счет
 * - Включение в реестр и отметка об оплате
 * - Импорт/экспорт
 * 
 * Особенности:
 * - Инициатор видит только свои платежи
 * - Админ/казначей/руководитель видят все платежи
 * - Автоматический сброс статуса при редактировании согласованных платежей
 * - Конвертация валют при переносе на другой счет
 */
class PaymentController extends Controller
{

    //Получить список всех платежей
    public function index()
    {
        $user = Auth::user();
        $query = Payment::with(['account', 'counterparty', 'item', 'creator']);

        if (!$user->hasAnyRole(['admin', 'treasurer', 'manager'])) {
            $query->where('created_by', $user->id);
        }

        return response()->json($query->get(), 200);
    }

    //Создать новую заявку на платеж
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'planned_date' => 'required|date',
            'account_id' => 'required|integer|exists:accounts,id',
            'item_id' => 'required|integer|exists:items,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'nullable|in:draft,under_approval,approved,approved_moved,in_registry,paid,rejected',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'draft';
        }

        $item = Item::find($validated['item_id']);
        if (!$item || $item->type !== 'expense') {
            return response()->json([
                'error' => 'Для платежа необходимо выбрать статью с типом "expense"'
            ], 422);
        }

        $validated['created_by'] = Auth::id();

        return response()->json(Payment::create($validated), 201);
    }

    //Получить платеж по ID
    public function show(int $id)
    {
        return response()->json(
            Payment::with(['account', 'counterparty', 'item', 'creator'])->findOrFail($id),
            200
        );
    }

    //Обновить платеж
    public function update(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('update', $payment);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'amount' => 'nullable|integer|min:1',
            'planned_date' => 'nullable|date',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'item_id' => 'nullable|integer|exists:items,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:draft,under_approval,approved,approved_moved,in_registry,paid,rejected',
        ]);

        if (in_array($payment->status, ['approved', 'approved_moved', 'in_registry', 'paid'])) {
            $validated['status'] = 'under_approval';
        }

        if ($payment->status === 'in_registry') {
            return response()->json([
                'warning' => 'Заявка находится в реестре. Она будет исключена из него.',
                'payment' => $payment
            ], 200);
        }

        $payment->update($validated);

        return response()->json($payment, 200);
    }

    //Удалить платеж
    public function destroy(int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('delete', $payment);

        $payment->delete();

        return response()->json(null, 204);
    }

    //Согласовать платеж 
    public function approve(int $id, Request $request)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('approve', $payment);

        if ($payment->status !== 'under_approval') {
            return response()->json([
                'error' => 'Заявка должна быть на согласовании'
            ], 422);
        }

        $payment->status = 'approved';
        $payment->save();

        \App\Models\Approval::create([
            'payment_id' => $payment->id,
            'user_id' => Auth::id(),
            'decision' => 'approved',
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Заявка согласована',
            'payment' => $payment
        ]);
    }

    //Отклонить платеж
    public function reject(int $id, Request $request)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('approve', $payment);

        $request->validate([
            'comment' => 'required|string'
        ]);

        $payment->status = 'rejected';
        $payment->save();

        \App\Models\Approval::create([
            'payment_id' => $payment->id,
            'user_id' => Auth::id(),
            'decision' => 'rejected',
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Заявка отклонена',
            'payment' => $payment
        ]);
    }

    //Получить историю согласований по платежу
    public function getApprovals(int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('view', $payment);

        $approvals = $payment->approvals()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'decision' => $approval->decision,
                    'decision_text' => $approval->decision === 'approved' ? 'Согласовано' : 'Отклонено',
                    'comment' => $approval->comment,
                    'user_id' => $approval->user_id,
                    'user_name' => $approval->user?->name,
                    'user_email' => $approval->user?->email,
                    'created_at' => $approval->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $approval->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'total_approvals' => $approvals->count(),
            'approvals' => $approvals,
        ], 200);
    }

    //Перенести платеж на другую дату
    public function reschedule(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('reschedule', $payment);

        $request->validate([
            'new_date' => 'required|date|after_or_equal:today',
            'apply_to_future' => 'nullable|boolean',
        ]);

        $newDate = Carbon::parse($request->new_date);
        $oldDate = Carbon::parse($payment->planned_date);
        $daysDiff = $newDate->diffInDays($oldDate, false);

        if ($daysDiff < 0 && $request->apply_to_future) {
            return response()->json([
                'error' => 'Нельзя перенести все будущие платежи на более раннюю дату'
            ], 422);
        }

        if ($request->apply_to_future && $payment->is_recurring && $payment->recurring_template_id) {
            $futurePayments = Payment::where('recurring_template_id', $payment->recurring_template_id)
                ->where('planned_date', '>=', $payment->planned_date)
                ->where('id', '!=', $payment->id)
                ->orderBy('planned_date', 'asc')
                ->get();

            foreach ($futurePayments as $future) {
                $newPlannedDate = Carbon::parse($future->planned_date)->addDays($daysDiff);

                if ($newPlannedDate->lt(now()->startOfDay())) {
                    continue;
                }

                $future->planned_date = $newPlannedDate;

                if (in_array($future->status, ['approved', 'approved_moved'])) {
                    $future->status = 'approved_moved';
                }

                $future->save();

                \App\Models\AuditLog::create([
                    'user_id' => Auth::id(),
                    'entity_type' => 'payment',
                    'entity_id' => $future->id,
                    'action' => 'reschedule',
                    'field_name' => 'planned_date',
                    'old_value' => $future->getOriginal('planned_date'),
                    'new_value' => $future->planned_date,
                ]);
            }
        }

        if (!$payment->original_planned_date) {
            $payment->original_planned_date = $payment->planned_date;
        }

        $payment->planned_date = $newDate;
        $payment->rescheduled_by = Auth::id();
        $payment->rescheduled_at = now();

        if ($payment->status === 'approved') {
            $payment->status = 'approved_moved';
        }

        $payment->save();

        $hasGap = $this->checkCashGap($payment);

        $response = [
            'message' => 'Заявка перенесена',
            'payment' => $payment,
            'warning' => $hasGap ? 'Внимание! Перенос создает кассовый разрыв' : null,
        ];

        if ($request->apply_to_future && isset($futurePayments)) {
            $response['future_payments_count'] = $futurePayments->count();
            $response['future_payments_message'] = "Перенесено {$futurePayments->count()} будущих платежей по шаблону";
        }

        return response()->json($response, 200);
    }

    //Перенести платеж на другой счет (с конвертацией валюты)
    public function changeAccount(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('update', $payment);

        $request->validate([
            'new_account_id' => 'required|integer|exists:accounts,id'
        ]);

        $newAccount = Accounts::findOrFail($request->new_account_id);
        $oldAccount = $payment->account;

        if ($oldAccount->currency_id != $newAccount->currency_id) {
            $currencyService = new CurrencyService();

            try {
                $payment->amount = $currencyService->convertToRub(
                    $oldAccount->currency_id,
                    $payment->amount,
                    $payment->planned_date
                );
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Ошибка конвертации: ' . $e->getMessage()
                ], 422);
            }
        }

        $payment->account_id = $request->new_account_id;
        $payment->save();

        $hasGap = $this->checkCashGap($payment);

        return response()->json([
            'message' => 'Заявка перенесена на другой счет',
            'payment' => $payment,
            'warning' => $hasGap ? 'Внимание! Перенос создает кассовый разрыв' : null,
        ]);
    }

    //Отметить платеж как оплаченный
    public function markAsPaid(int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('markAsPaid', $payment);

        if (!in_array($payment->status, ['in_registry', 'approved', 'approved_moved'])) {
            return response()->json([
                'error' => 'Заявка должна быть в статусе "В реестре" или "Согласована"'
            ], 422);
        }

        $payment->status = 'paid';
        $payment->save();

        \App\Models\AuditLog::create([
            'user_id' => Auth::id(),
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'action' => 'status_change',
            'field_name' => 'status',
            'old_value' => $payment->getOriginal('status'),
            'new_value' => 'paid',
        ]);

        return response()->json([
            'message' => 'Заявка отмечена как оплаченная',
            'payment' => $payment
        ]);
    }

    //Импорт платежей
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
            $import = new PaymentImport($accountId);
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
            'message' => 'Импорт платежей завершен',
            'imported_count' => $import->getCreatedCount(),
        ];

        if ($import->getErrors()) {
            $response['errors'] = $import->getErrors();
            $response['error_count'] = count($import->getErrors());
        }

        return response()->json($response);
    }

    //Предварительный просмотр импорта платежей
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'account_id' => 'required|integer|exists:accounts,id',
        ]);

        $file = $request->file('file');

        $tempFile = tempnam(sys_get_temp_dir(), 'import_') . '.xlsx';
        file_put_contents($tempFile, file_get_contents($file->getRealPath()));

        try {
            $rows = SimpleExcelReader::create($tempFile)->getRows();

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

            $rows = null;
            usleep(100000);

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return response()->json([
                'total_rows' => $totalRows,
                'preview_rows' => count($preview),
                'preview' => $preview,
                'columns' => $preview ? array_keys($preview[0]['data']) : [],
            ], 200);
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return response()->json([
                'error' => 'Ошибка чтения файла: ' . $e->getMessage()
            ], 422);
        }
    }

    //Проверить, создает ли платеж кассовый разрыв
    private function checkCashGap(Payment $payment): bool
    {
        return $this->calculateBalance($payment->account_id, $payment->planned_date) < 0;
    }

    //Рассчитать баланс на дату
    private function calculateBalance(int $accountId, string $date): int
    {
        $account = Accounts::findOrFail($accountId);
        $balance = $account->initial_balance;

        $incomes = Income::where('account_id', $accountId)
            ->where('planned_date', '<=', $date)
            ->sum('amount');

        $payments = Payment::where('account_id', $accountId)
            ->where('planned_date', '<=', $date)
            ->whereIn('status', ['approved', 'approved_moved', 'in_registry', 'paid', 'under_approval'])
            ->sum('amount');

        return $balance + $incomes - $payments;
    }

    //Валидация строки для импорта
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
