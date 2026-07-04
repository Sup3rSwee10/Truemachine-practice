<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Контроллер журнала аудита
 * 
 * Предоставляет доступ к истории изменений в системе:
 * - Просмотр всех записей аудита с фильтрацией
 * - Получение истории по конкретному платежу
 * - Детальный просмотр записи аудита
 * 
 * Доступен только для ролей admin и manager 
 */
class AuditLogController extends Controller
{
    /**
     * Получить список записей аудита с фильтрацией и пагинацией
     * 
     * Доступные фильтры:
     * - entity_type: тип сущности 
     * - entity_id: ID сущности
     * - action: действие (create, update, delete, status_change, reschedule, approve, reject)
     * - user_id: ID пользователя
     * - date_from: дата начала
     * - date_to: дата окончания
     * - sort_field: поле для сортировки
     * - sort_direction: направление сортировки (asc/desc)
     * - per_page: количество записей на странице
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Фильтр по типу сущности 
        if ($request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        // Фильтр по ID сущности 
        if ($request->entity_id) {
            $query->where('entity_id', $request->entity_id);
        }

        // Фильтр по действию (create, update, delete, status_change, reschedule, approve, reject)
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Фильтр по пользователю, выполнившему действие
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Фильтр по диапазону дат
        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Сортировка (по умолчанию: новые записи сверху)
        $sortField = $request->sort_field ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        // Пагинация (по умолчанию 20 записей на страницу)
        $perPage = $request->per_page ?? 20;
        $logs = $query->paginate($perPage);

        // Форматируем ответ
        return response()->json([
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'user_email' => $log->user?->email,
                    'entity_type' => $log->entity_type,
                    'entity_type_text' => $log->entity_type === 'payment' ? 'Платёж' : $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'action' => $log->action,
                    'action_text' => $log->action_text,
                    'field_name' => $log->field_name,
                    'field_name_text' => $this->getFieldNameText($log->field_name),
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $log->created_at->diffForHumans(),
                ];
            }),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ], 200);
    }

    //Получить детальную информацию о записи аудита по ID
    public function show(int $id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json([
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name,
            'user_email' => $log->user?->email,
            'entity_type' => $log->entity_type,
            'entity_type_text' => $log->entity_type === 'payment' ? 'Платёж' : $log->entity_type,
            'entity_id' => $log->entity_id,
            'action' => $log->action,
            'action_text' => $log->action_text,
            'field_name' => $log->field_name,
            'field_name_text' => $this->getFieldNameText($log->field_name),
            'old_value' => $log->old_value,
            'new_value' => $log->new_value,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $log->created_at->diffForHumans(),
        ], 200);
    }

    /**
     * Получить всю историю изменений по конкретному платежу
     * 
     * Показывает все действия, связанные с указанным платежом:
     * - Создание
     * - Редактирования полей
     * - Смена статуса
     * - Переносы
     * - Согласования
     */
    public function getPaymentHistory(int $paymentId)
    {
        $logs = AuditLog::where('entity_type', 'payment')
            ->where('entity_id', $paymentId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payment_id' => $paymentId,
            'total' => $logs->count(),
            'logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name,
                    'action' => $log->action,
                    'action_text' => $log->action_text,
                    'field_name' => $log->field_name,
                    'field_name_text' => $this->getFieldNameText($log->field_name),
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $log->created_at->diffForHumans(),
                ];
            }),
        ], 200);
    }

    //Используется для отображения названия измененного поля в журнале аудита
    private function getFieldNameText(?string $fieldName): string
    {
        if (!$fieldName) {
            return '—';
        }

        $fields = [
            'name' => 'Название',
            'amount' => 'Сумма',
            'planned_date' => 'Дата платежа',
            'original_planned_date' => 'Исходная дата',
            'account_id' => 'Счёт',
            'counterparty_id' => 'Контрагент',
            'item_id' => 'Статья',
            'priority' => 'Приоритет',
            'status' => 'Статус',
            'rescheduled_by' => 'Кто перенёс',
            'rescheduled_at' => 'Дата переноса',
            'is_recurring' => 'Повторяющийся',
            'recurring_template_id' => 'Шаблон',
            'created_by' => 'Создатель',
        ];

        return $fields[$fieldName] ?? $fieldName;
    }
}
