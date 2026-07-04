<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $table = 'incomes';
    protected $guarded = [];

    protected $casts = [
        'planned_date' => 'date',
        'is_recurring' => 'boolean',
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

    public function recurringTemplate()
    {
        return $this->belongsTo(RecurringTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
