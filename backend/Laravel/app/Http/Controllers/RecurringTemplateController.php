<?php

namespace App\Http\Controllers;

use App\Models\RecurringTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Контроллер управления шаблонами повторяющихся платежей и поступлений
 * 
 * Обеспечивает:
 * - Создание шаблонов для регулярных платежей и поступлений
 * - Редактирование и удаление шаблонов
 * - Просмотр всех шаблонов (инициатор видит только свои)
 * - Ручную генерацию заявок по шаблону
 * - Просмотр сгенерированных платежей и поступлений
 * 
 * Особенности:
 * - Шаблоны могут быть для платежей (type='payment') и поступлений (type='income')
 * - Для шаблонов платежей обязательно указывается приоритет
 * - Поддерживаются периодичности: daily, weekly, monthly
 * - Автоматический перенос дат с выходных на пятницу
 * - Инициатор видит только свои шаблоны
 */
class RecurringTemplateController extends Controller
{
    //Получить список всех шаблонов
    public function index()
    {
        $user = Auth::user();
        $query = RecurringTemplate::with(['account', 'counterparty', 'item', 'creator']);

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

        $templates = $query->get();

        return response()->json([
            'total' => $templates->count(),
            'templates' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'type' => $template->type,
                    'type_text' => $template->type === 'payment' ? 'Платёж' : 'Поступление',
                    'name' => $template->name,
                    'amount' => $template->amount,
                    'amount_formatted' => number_format($template->amount / 100, 2, '.', ' ') . ' ₽',
                    'account' => $template->account?->name,
                    'counterparty' => $template->counterparty?->name,
                    'item' => $template->item?->name,
                    'priority' => $template->priority,
                    'priority_text' => $this->getPriorityText($template->priority),
                    'frequency' => $template->frequency,
                    'frequency_text' => $this->getFrequencyText($template->frequency),
                    'start_date' => $template->start_date?->format('Y-m-d'),
                    'end_date' => $template->end_date?->format('Y-m-d'),
                    'last_generated_date' => $template->last_generated_date?->format('Y-m-d'),
                    'is_active' => $this->isActive($template),
                    'created_by' => $template->created_by,
                    'creator_name' => $template->creator?->name,
                    'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ], 200);
    }

    //Создать новый шаблон
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:payment,income',
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'account_id' => 'required|integer|exists:accounts,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'item_id' => 'required|integer|exists:items,id',
            'priority' => 'nullable|in:low,medium,high',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($data['type'] === 'income') {
            $data['priority'] = null;
        }

        if ($data['type'] === 'payment' && empty($data['priority'])) {
            return response()->json([
                'error' => 'Для шаблона платежа необходимо указать priority'
            ], 422);
        }

        $data['created_by'] = Auth::id();

        $template = RecurringTemplate::create($data);

        return response()->json([
            'message' => 'Шаблон создан',
            'template' => $template->load(['account', 'counterparty', 'item', 'creator'])
        ], 201);
    }

    //Получить шаблон по ID
    public function show(int $id)
    {
        $template = RecurringTemplate::with(['account', 'counterparty', 'item', 'creator'])->findOrFail($id);

        return response()->json([
            'id' => $template->id,
            'type' => $template->type,
            'type_text' => $template->type === 'payment' ? 'Платёж' : 'Поступление',
            'name' => $template->name,
            'amount' => $template->amount,
            'amount_formatted' => number_format($template->amount / 100, 2, '.', ' ') . ' ₽',
            'account' => $template->account,
            'counterparty' => $template->counterparty,
            'item' => $template->item,
            'priority' => $template->priority,
            'priority_text' => $this->getPriorityText($template->priority),
            'frequency' => $template->frequency,
            'frequency_text' => $this->getFrequencyText($template->frequency),
            'start_date' => $template->start_date->format('Y-m-d'),
            'end_date' => $template->end_date?->format('Y-m-d'),
            'last_generated_date' => $template->last_generated_date?->format('Y-m-d'),
            'is_active' => $this->isActive($template),
            'generated_payments_count' => $template->payments()->count(),
            'generated_incomes_count' => $template->incomes()->count(),
            'created_by' => $template->created_by,
            'creator_name' => $template->creator?->name,
            'created_at' => $template->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $template->updated_at->format('Y-m-d H:i:s'),
        ], 200);
    }

    //Обновить шаблон
    public function update(Request $request, int $id)
    {
        $template = RecurringTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|in:payment,income',
            'name' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|integer|min:1',
            'account_id' => 'sometimes|required|integer|exists:accounts,id',
            'counterparty_id' => 'nullable|integer|exists:counterparties,id',
            'item_id' => 'sometimes|required|integer|exists:items,id',
            'priority' => 'nullable|in:low,medium,high',
            'frequency' => 'sometimes|required|in:daily,weekly,monthly',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if (isset($data['type']) && $data['type'] === 'income') {
            $data['priority'] = null;
        }

        $template->update($data);

        return response()->json([
            'message' => 'Шаблон обновлён',
            'template' => $template->load(['account', 'counterparty', 'item', 'creator'])
        ], 200);
    }

    //Удалить шаблон
    public function destroy(int $id)
    {
        $template = RecurringTemplate::findOrFail($id);

        $paymentsCount = $template->payments()->count();
        $incomesCount = $template->incomes()->count();

        if ($paymentsCount > 0 || $incomesCount > 0) {
            return response()->json([
                'error' => 'Нельзя удалить шаблон, так как по нему уже созданы заявки',
                'payments_count' => $paymentsCount,
                'incomes_count' => $incomesCount,
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Шаблон удалён'
        ], 200);
    }

    //Ручная генерация заявок по шаблону
    public function generate(Request $request, int $id)
    {
        $template = RecurringTemplate::findOrFail($id);

        $command = new \App\Console\Commands\GenerateRecurringPayments();
        $generated = $command->generateForTemplate($template);

        return response()->json([
            'message' => 'Заявки сгенерированы',
            'template_id' => $template->id,
            'template_name' => $template->name,
            'generated_count' => $generated,
        ], 200);
    }

    //Получить все сгенерированные платежи по шаблону
    public function getGeneratedPayments(int $id)
    {
        $template = RecurringTemplate::findOrFail($id);

        $payments = $template->payments()
            ->with(['account', 'counterparty', 'item'])
            ->orderBy('planned_date', 'desc')
            ->get();

        return response()->json([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'total' => $payments->count(),
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'amount_formatted' => number_format($payment->amount / 100, 2, '.', ' ') . ' ₽',
                    'planned_date' => $payment->planned_date->format('Y-m-d'),
                    'status' => $payment->status,
                    'status_text' => $payment->formatted_status,
                    'account' => $payment->account?->name,
                    'counterparty' => $payment->counterparty?->name,
                    'item' => $payment->item?->name,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ], 200);
    }

    //Получить все сгенерированные поступления по шаблону
    public function getGeneratedIncomes(int $id)
    {
        $template = RecurringTemplate::findOrFail($id);

        $incomes = $template->incomes()
            ->with(['account', 'counterparty', 'item'])
            ->orderBy('planned_date', 'desc')
            ->get();

        return response()->json([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'total' => $incomes->count(),
            'incomes' => $incomes->map(function ($income) {
                return [
                    'id' => $income->id,
                    'amount' => $income->amount,
                    'amount_formatted' => number_format($income->amount / 100, 2, '.', ' ') . ' ₽',
                    'planned_date' => $income->planned_date->format('Y-m-d'),
                    'account' => $income->account?->name,
                    'counterparty' => $income->counterparty?->name,
                    'item' => $income->item?->name,
                    'created_at' => $income->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ], 200);
    }

    //Получить текстовое описание приоритета
    private function getPriorityText(?string $priority): string
    {
        return [
            'low' => 'Низкий',
            'medium' => 'Средний',
            'high' => 'Высокий',
        ][$priority] ?? '—';
    }

    //Получить текстовое описание периодичности
    private function getFrequencyText(string $frequency): string
    {
        return [
            'daily' => 'Ежедневно',
            'weekly' => 'Еженедельно',
            'monthly' => 'Ежемесячно',
        ][$frequency] ?? $frequency;
    }

    /**
     * Проверить, активен ли шаблон
     * 
     * Шаблон активен если:
     * - start_date <= сегодня
     * - end_date >= сегодня (или NULL)
     */
    private function isActive(RecurringTemplate $template): bool
    {
        $today = Carbon::today();

        if ($template->start_date > $today) {
            return false;
        }

        if ($template->end_date && $template->end_date < $today) {
            return false;
        }

        return true;
    }
}
