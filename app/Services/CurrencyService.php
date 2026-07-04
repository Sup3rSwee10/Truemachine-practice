<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;

/**
 * Сервис для работы с курсами валют
 * 
 * Обеспечивает:
 * - Получение актуальных курсов с API ЦБ РФ
 * - Сохранение курсов в базу данных
 * - Конвертацию сумм из одной валюты в рубли
 * 
 * Поддерживаемые валюты: USD, EUR, AMD
 * Источник данных: https://www.cbr-xml-daily.ru/daily_json.js
 */
class CurrencyService
{
    private const RUB_CURRENCY_ID = 1;

    private const CURRENCY_MAP = [
        'USD' => 2,
        'EUR' => 3,
        'AMD' => 4,
    ];

    //Получить актуальные курсы валют с API ЦБ РФ
    public function fetchRatesFromExchange(): ?array
    {
        $response = Http::withoutVerifying()->get('https://www.cbr-xml-daily.ru/daily_json.js');

        if ($response->successful()) {
            $data = $response->json();
            $valute = $data['Valute'] ?? [];

            $usd = $valute['USD']['Value'] ?? null;
            $eur = $valute['EUR']['Value'] ?? null;

            $amdRaw = $valute['AMD']['Value'] ?? null;
            $amdNominal = $valute['AMD']['Nominal'] ?? 1;
            $amd = $amdRaw ? ($amdRaw / $amdNominal) : null;

            return [
                'USD' => $usd,
                'EUR' => $eur,
                'AMD' => $amd,
            ];
        }

        return null;
    }

    /**
     * Обновить курсы валют в базе данных
     * 
     * Получает актуальные курсы с API и сохраняет в таблицу exchange_rates
     */
    public function updateRatesInDatabase(): array
    {
        $rates = $this->fetchRatesFromExchange();

        if (!$rates) {
            throw new Exception("Не удалось получить курсы валют.");
        }

        $today = Carbon::today()->format('Y-m-d');

        foreach (self::CURRENCY_MAP as $code => $currencyId) {
            if (isset($rates[$code])) {
                ExchangeRate::updateOrCreate(
                    [
                        'currency_id' => $currencyId,
                        'rate_date' => $today,
                    ],
                    [
                        'rate_to_rub' => $rates[$code],
                    ]
                );
            }
        }

        return $rates;
    }

    /**
     * Конвертировать сумму из указанной валюты в рубли
     * 
     * Используется при переносе платежа на счет с другой валютой
     */
    public function convertToRub(int $currencyId, int $amount, string $date): int
    {
        if ($currencyId === self::RUB_CURRENCY_ID) {
            return $amount;
        }

        $rate = ExchangeRate::where('currency_id', $currencyId)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        if (!$rate) {
            throw new Exception("Курс валюты для ID {$currencyId} на дату {$date} не найден в БД!");
        }

        return (int) round($amount * $rate->rate_to_rub);
    }
}
