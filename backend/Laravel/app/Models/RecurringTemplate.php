<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RecurringTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_generated_date' => 'date',
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

    public function payments()
    {
        return $this->hasMany(Payment::class, 'recurring_template_id');
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'recurring_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        $today = now()->startOfDay();

        if ($this->start_date > $today) {
            return false;
        }

        if ($this->end_date && $this->end_date < $today) {
            return false;
        }

        return true;
    }

    public function getNextGenerationDate(): ?string
    {
        if (!$this->isActive()) {
            return null;
        }

        $lastDate = $this->last_generated_date
            ? Carbon::parse($this->last_generated_date)
            : Carbon::parse($this->start_date);

        switch ($this->frequency) {
            case 'daily':
                $nextDate = $lastDate->addDay();
                break;
            case 'weekly':
                $nextDate = $lastDate->addWeek();
                break;
            case 'monthly':
                $nextDate = $lastDate->addMonth();
                break;
            default:
                return null;
        }

        if ($this->end_date && $nextDate > $this->end_date) {
            return null;
        }

        return $nextDate->format('Y-m-d');
    }
}
