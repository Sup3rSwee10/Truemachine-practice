<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\RecurringTemplateController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

/*
маршруты API для платёжного календаря.
Все маршруты (кроме /login) требуют аутентификации через Sanctum.
*/

// ПУБЛИЧНЫЕ МАРШРУТЫ

/**
 * Вход в систему
 */
Route::post('login', [AuthController::class, 'login']);


// ЗАЩИЩЁННЫЕ МАРШРУТЫ (требуется токен)
Route::middleware('auth:sanctum')->group(function () {

    //  АУТЕНТИФИКАЦИЯ 
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);


    //  СПРАВОЧНИКИ 

    /** Просмотр справочников (все авторизованные) */
    Route::apiResource('counterparties', CounterpartyController::class)->only(['index', 'show']);
    Route::apiResource('items', ItemController::class)->only(['index', 'show']);
    Route::apiResource('accounts', AccountsController::class)->only(['index', 'show']);

    /** Валюты и курсы (все авторизованные) */
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::get('currencies/{id}', [CurrencyController::class, 'show']);
    Route::prefix('currencies')->group(function () {
        Route::get('rates/latest', [CurrencyController::class, 'getLatestRates']);
        Route::get('{identifier}/rate', [CurrencyController::class, 'getRate']);
        Route::get('{identifier}/history', [CurrencyController::class, 'getRateHistory']);
    });

    /** Управление справочниками (только администратор) */
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('counterparties', CounterpartyController::class)->except(['index', 'show']);
        Route::apiResource('items', ItemController::class)->except(['index', 'show']);
        Route::apiResource('accounts', AccountsController::class)->except(['index', 'show']);
    });


    //  ПЛАТЁЖНЫЙ КАЛЕНДАРЬ 
    Route::get('calendar', [CalendarController::class, 'getCalendar']);
    Route::get('calendar/cash-gaps', [CalendarController::class, 'getCashGaps']);
    Route::get('calendar/total-balance', [CalendarController::class, 'getTotalBalance']);


    //  ОТЧЁТЫ 

    /** Формирование отчетов (все авторизованные) */
    Route::get('reports/balances', [ReportController::class, 'balances']);
    Route::get('reports/cash-gaps', [ReportController::class, 'cashGapsReport']);
    Route::get('reports/plan-fact', [ReportController::class, 'planFact']);

    /** Экспорт отчетов в Excel */
    Route::get('reports/export/balances', [ReportController::class, 'exportBalances']);
    Route::get('reports/export/cash-gaps', [ReportController::class, 'exportCashGaps']);
    Route::get('reports/export/plan-fact', [ReportController::class, 'exportPlanFact']);

    /** История и скачивание отчетов (казначей, руководитель, админ) */
    Route::middleware(['role:treasurer,manager,admin'])->group(function () {
        Route::get('reports/history', [ReportController::class, 'history']);
        Route::get('reports/download/{id}', [ReportController::class, 'download']);
    });


    //  ПЛАТЕЖИ 

    /** Управление платежами (инициатор, казначей, руководитель, админ) */
    Route::middleware(['role:initiator,treasurer,manager,admin'])->group(function () {
        Route::apiResource('payments', PaymentController::class);
        Route::post('payments/import', [PaymentController::class, 'importExcel']);
        Route::post('payments/import/preview', [PaymentController::class, 'importPreview']);
    });


    //  ПОСТУПЛЕНИЯ 

    /** Управление поступлениями (инициатор, казначей, руководитель, админ) */
    Route::middleware(['role:initiator,treasurer,manager,admin'])->group(function () {
        Route::apiResource('incomes', IncomeController::class);
        Route::post('incomes/import', [IncomeController::class, 'importExcel']);
        Route::post('incomes/import/preview', [IncomeController::class, 'importPreview']);
    });


    //  СОГЛАСОВАНИЕ 

    /** Согласование платежей (казначей, руководитель, админ) */
    Route::middleware(['role:treasurer,manager,admin'])->group(function () {
        Route::post('payments/{id}/approve', [PaymentController::class, 'approve']);
        Route::post('payments/{id}/reject', [PaymentController::class, 'reject']);
        Route::post('payments/{id}/mark-as-paid', [PaymentController::class, 'markAsPaid']);
    });

    /** История согласований (все авторизованные) */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('payments/{id}/approvals', [PaymentController::class, 'getApprovals']);
    });


    //  ПЕРЕНОС ПЛАТЕЖЕЙ 

    /** Перенос платежей (казначей, админ) */
    Route::middleware(['role:treasurer,admin'])->group(function () {
        Route::post('payments/{id}/reschedule', [PaymentController::class, 'reschedule']);
        Route::post('payments/{id}/change-account', [PaymentController::class, 'changeAccount']);
    });


    //  РЕЕСТРЫ 

    /** Управление реестрами (казначей, руководитель, админ) */
    Route::middleware(['role:treasurer,manager,admin'])->group(function () {
        Route::get('registries', [RegistryController::class, 'index']);
        Route::post('registries', [RegistryController::class, 'store']);
        Route::get('registries/{id}', [RegistryController::class, 'show']);
        Route::put('registries/{id}', [RegistryController::class, 'update']);
        Route::delete('registries/{id}', [RegistryController::class, 'destroy']);
        Route::post('registries/{id}/attach', [RegistryController::class, 'attachPayments']);
        Route::get('registries/{id}/export', [RegistryController::class, 'export']);
    });

    /** Утверждение реестра (только руководитель и админ) */
    Route::middleware(['role:manager,admin'])->group(function () {
        Route::post('registries/{id}/approve', [RegistryController::class, 'approve']);
    });

    /** Отправка реестра в банк (только руководитель и админ) */
    Route::middleware(['role:manager,admin'])->group(function () {
        Route::post('registries/{id}/send-to-bank', [RegistryController::class, 'sendToBank']);
    });


    //  ПОВТОРЯЮЩИЕСЯ ПЛАТЕЖИ 

    /** Управление шаблонами (инициатор, казначей, админ) */
    Route::middleware(['role:initiator,treasurer,admin,manager'])->group(function () {
        Route::apiResource('recurring-templates', RecurringTemplateController::class);
        Route::post('recurring-templates/{id}/generate', [RecurringTemplateController::class, 'generate']);
        Route::get('recurring-templates/{id}/payments', [RecurringTemplateController::class, 'getGeneratedPayments']);
        Route::get('recurring-templates/{id}/incomes', [RecurringTemplateController::class, 'getGeneratedIncomes']);
    });


    //  КУРСЫ ВАЛЮТ 

    /** Управление курсами валют (только админ) */
    Route::middleware(['role:admin'])->group(function () {
        Route::get('rates', [ExchangeRateController::class, 'index']);
        Route::post('rates/update', [ExchangeRateController::class, 'updateRates']);
    });


    //  ЖУРНАЛ АУДИТА 

    /** Просмотр аудита (админ, руководитель) */
    Route::middleware(['auth:sanctum', 'role:admin,manager'])->prefix('audit')->group(function () {
        Route::get('logs', [AuditLogController::class, 'index']);
        Route::get('logs/{id}', [AuditLogController::class, 'show']);
        Route::get('payment/{paymentId}', [AuditLogController::class, 'getPaymentHistory']);
    });
});

// АДМИНИСТРИРОВАНИЕ ПОЛЬЗОВАТЕЛЕЙ
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
    Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword']);
});
