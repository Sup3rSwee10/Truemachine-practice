<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Контроллер управления валютами и курсами
 * 
 * Обеспечивает:
 * - Просмотр списка всех валют
 * - Получение актуальных курсов валют
 * - Историю изменения курсов
 * 
 * Доступ: все авторизованные пользователи (просмотр)
 */
class CurrencyController extends Controller
{

    //Получить список всех валют
    public function index()
    {
        return response()->json(Currency::all(), 200);
    }

    //Получить валюту по ID
    public function show(int $id)
    {
        $currency = Currency::findOrFail($id);
        return response()->json($currency, 200);
    }

    /**
     * Получить актуальный курс валюты (последний на сегодня)
     * 
     * Поддерживает два формата идентификатора:
     * - Числовой: /api/currencies/2/rate (по ID)
     * - Строковый: /api/currencies/USD/rate (по коду валюты)
     */
    public function getRate($identifier)
    {
        if (is_numeric($identifier)) {
            $currency = Currency::findOrFail($identifier);
        } else {
            $currency = Currency::where('code', strtoupper($identifier))->firstOrFail();
        }

        $rate = ExchangeRate::where('currency_id', $currency->id)
            ->orderBy('rate_date', 'desc')
            ->first();

        if (!$rate) {
            return response()->json([
                'error' => 'Курс для валюты ' . $currency->code . ' не найден'
            ], 404);
        }

        return response()->json([
            'currency' => $currency,
            'rate' => [
                'id' => $rate->id,
                'rate_to_rub' => $rate->rate_to_rub,
                'rate_date' => $rate->rate_date,
                'rate_to_rub_formatted' => number_format($rate->rate_to_rub, 4, '.', ' ') . ' ₽',
                'created_at' => $rate->created_at,
            ],
        ], 200);
    }

    //Получить актуальные курсы всех валют
    public function getLatestRates()
    {
        $currencies = Currency::all();
        $result = [];

        foreach ($currencies as $currency) {
            $rate = ExchangeRate::where('currency_id', $currency->id)
                ->orderBy('rate_date', 'desc')
                ->first();

            if ($rate) {
                $result[] = [
                    'currency' => $currency,
                    'rate_to_rub' => $rate->rate_to_rub,
                    'rate_to_rub_formatted' => number_format($rate->rate_to_rub, 4, '.', ' ') . ' ₽',
                    'rate_date' => $rate->rate_date,
                ];
            }
        }

        return response()->json([
            'date' => Carbon::today()->toDateString(),
            'rates' => $result,
        ], 200);
    }

    /**
     * Получить историю курсов для валюты за указанное количество дней
     * 
     * Поддерживает два формата идентификатора:
     * - Числовой: /api/currencies/2/history?days=30 (по ID)
     * - Строковый: /api/currencies/USD/history?days=30 (по коду валюты)
     */
    public function getRateHistory(Request $request, $identifier)
    {
        $days = $request->days ?? 30;

        if (is_numeric($identifier)) {
            $currency = Currency::findOrFail($identifier);
        } else {
            $currency = Currency::where('code', strtoupper($identifier))->firstOrFail();
        }

        $rates = ExchangeRate::where('currency_id', $currency->id)
            ->where('rate_date', '>=', Carbon::today()->subDays($days))
            ->orderBy('rate_date', 'asc')
            ->get()
            ->map(function ($rate) {
                return [
                    'date' => $rate->rate_date,
                    'rate_to_rub' => $rate->rate_to_rub,
                    'rate_to_rub_formatted' => number_format($rate->rate_to_rub, 4, '.', ' ') . ' ₽',
                ];
            });

        return response()->json([
            'currency' => $currency,
            'period' => [
                'days' => $days,
                'start' => Carbon::today()->subDays($days)->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ],
            'total_rates' => $rates->count(),
            'rates' => $rates,
        ], 200);
    }
}
