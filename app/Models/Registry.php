<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registry extends Model
{
    protected $table = 'registries';
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'registry_payment', 'registry_id', 'payment_id');
    }
}
