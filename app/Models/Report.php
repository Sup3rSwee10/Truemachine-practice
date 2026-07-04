<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'parameters' => 'json',
        'created_at' => 'datetime',
    ];

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getTypeTextAttribute(): string
    {
        return [
            'balances' => 'Балансы',
            'cash_gaps' => 'Кассовые разрывы',
            'plan_fact' => 'План-Факт',
        ][$this->type] ?? $this->type;
    }

    public function getExtensionAttribute(): string
    {
        return [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/pdf' => 'pdf',
        ][$this->mime_type] ?? 'bin';
    }
}
