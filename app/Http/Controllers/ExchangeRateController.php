<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Currency;
use App\Services\CurrencyService;
use Carbon\Carbon;

/**
 * Контроллер управления курсами валют
 * 
 * Обеспечивает:
 * - Просмотр всех сохраненных курсов
 * - Принудительное обновление курсов
 * 
 * Доступ: только для администратора
 */
class ExchangeRateController extends Controller
{

    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Получить всю историю сохраненных курсов
     * 
     * Возвращает все записи курсов с привязкой к валютам
     */
    public function index()
    {
        return response()->json(ExchangeRate::with('currency')->get(), 200);
    }

    /**
     * Запустить обновление курсов валют
     * 
     * Загружает актуальные курсы с сайта ЦБ РФ и сохраняет в БД
     * Поддерживаемые валюты: USD, EUR, AMD
     */
    public function updateRates()
    {
        $rates = $this->currencyService->fetchRatesFromExchange();

        if (!$rates) {
            return response()->json([
                'message' => 'Не удалось получить актуальные курсы'
            ], 500);
        }

        foreach ($rates as $code => $value) {
            if ($value) {
                $name = '';
                $symbol = '';

                if ($code === 'USD') {
                    $name = 'Доллар США';
                    $symbol = '$';
                }
                if ($code === 'EUR') {
                    $name = 'Евро';
                    $symbol = '€';
                }
                if ($code === 'AMD') {
                    $name = 'Армянский драм';
                    $symbol = '֏';
                }

                $currency = Currency::firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'symbol' => $symbol]
                );

                ExchangeRate::updateOrCreate(
                    [
                        'currency_id' => $currency->id,
                        'rate_date' => Carbon::today()->toDateString()
                    ],
                    [
                        'rate_to_rub' => round($value, 6)
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Курсы валют (USD, EUR, AMD) успешно синхронизированы с биржи!',
            'date' => Carbon::today()->toDateString(),
            'rates_in_rub' => [
                '1 USD' => round($rates['USD'], 2) . ' ₽',
                '1 EUR' => round($rates['EUR'], 2) . ' ₽',
                '1 AMD' => round($rates['AMD'], 4) . ' ₽'
            ]
        ], 200);
    }
}
