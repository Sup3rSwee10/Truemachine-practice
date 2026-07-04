<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    protected $guarded = [];

    const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActionTextAttribute(): string
    {
        $actions = [
            'create' => 'Создание',
            'update' => 'Обновление',
            'delete' => 'Удаление',
            'status_change' => 'Смена статуса',
            'reschedule' => 'Перенос',
            'approve' => 'Согласование',
            'reject' => 'Отклонение',
        ];
        return $actions[$this->action] ?? $this->action;
    }
}
