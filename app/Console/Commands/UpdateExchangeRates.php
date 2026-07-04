<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

/**
 * Команда для обновления курсов валют из внешнего источника
 * 
 * Запускается автоматически по расписанию (ежедневно в 06:00 и 15:00)
 * или вручную для принудительного обновления
 */
class UpdateExchangeRates extends Command
{
    protected $signature = 'rates:update';

    protected $description = 'Обновление курсов валют из внешнего источника (ЦБ РФ)';

    public function handle(CurrencyService $currencyService): int
    {
        $this->info('обновление курсов валют...');

        try {
            $rates = $currencyService->updateRatesInDatabase();

            $this->info('Курсы валют успешно обновлены!');
            $this->newLine();
            $this->table(
                ['Валюта', 'Курс к RUB'],
                [
                    ['1 USD', $this->formatRate($rates['USD'], 2) . ' ₽'],
                    ['1 EUR', $this->formatRate($rates['EUR'], 2) . ' ₽'],
                    ['1 AMD', $this->formatRate($rates['AMD'], 4) . ' ₽'],
                ]
            );
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка обновления курсов: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function formatRate(?float $rate, int $precision): string
    {
        if ($rate === null) {
            return '—';
        }

        return number_format(round($rate, $precision), $precision, '.', ' ');
    }
}
