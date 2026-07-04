<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Payment;
use App\Policies\PaymentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Payment::class => PaymentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
