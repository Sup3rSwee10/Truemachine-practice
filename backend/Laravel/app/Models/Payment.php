<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Payment extends Model
{
    protected $table = 'payments';
    protected $guarded = [];

    protected $casts = [
        'planned_date' => 'date',
        'original_planned_date' => 'date',
        'is_recurring' => 'boolean',
        'rescheduled_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Accounts::class);
    }

    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function registry()
    {
        return $this->belongsTo(Registry::class, 'registry_id');
    }

    public function rescheduledBy()
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function recurringTemplate()
    {
        return $this->belongsTo(RecurringTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'payment_id');
    }

    public function latestApproval()
    {
        return $this->hasOne(Approval::class, 'payment_id')->latest();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' || $this->status === 'approved_moved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getFormattedStatusAttribute(): string
    {
        $statuses = [
            'draft' => 'Черновик',
            'under_approval' => 'На согласовании',
            'approved' => 'Согласована',
            'approved_moved' => 'Согласована с переносом',
            'in_registry' => 'В реестре',
            'paid' => 'Оплачена',
            'rejected' => 'Отклонена',
        ];

        $currentStatus = $statuses[$this->status] ?? $this->status;

        if (in_array($this->status, ['approved', 'approved_moved']) && !empty($this->original_planned_date)) {
            return "Согласовано, перенесено с " . Carbon::parse($this->original_planned_date)->format('d.m.Y');
        }

        return $currentStatus;
    }
}
