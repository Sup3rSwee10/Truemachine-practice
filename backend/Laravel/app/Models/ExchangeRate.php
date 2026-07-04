<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
