<?php

namespace App\Exports;

use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Экспорт финансовых отчетов 
 * 
 * Содержит методы для выгрузки различных типов отчетов:
 * - Балансы по счетам
 * - Кассовые разрывы
 * - План-факт
 */
class ReportExport
{
    public static function balances($data, string $date)
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="balances_' . $date . '.xlsx"',
        ];

        $callback = function () use ($data) {
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');

            $writer->addRow([
                'Счет',
                'Валюта',
                'Баланс (коп.)',
                'Баланс (₽)'
            ]);

            foreach ($data->accounts as $account) {
                $writer->addRow([
                    $account->account_name,
                    $account->currency,
                    $account->balance,
                    $account->balance_formatted,
                ]);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    public static function cashGaps($data, string $startDate, string $endDate)
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="cash_gaps_' . $startDate . '_to_' . $endDate . '.xlsx"',
        ];

        $callback = function () use ($data) {
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');

            $writer->addRow([
                'ID счета',
                'Название счета',
                'Дата',
                'Баланс (коп.)',
                'Дефицит (коп.)'
            ]);

            foreach ($data->cash_gaps as $gap) {
                $writer->addRow([
                    $gap->account_id,
                    $gap->account_name,
                    $gap->date,
                    $gap->balance_end,
                    $gap->deficit,
                ]);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    public static function planFact($data, string $startDate, string $endDate)
    {

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plan_fact_' . $startDate . '_to_' . $endDate . '.xlsx"',
        ];

        $callback = function () use ($data) {
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');

            $writer->addRow(['Отчет План-Факт']);
            $writer->addRow(['Период:', $data->period->start . ' - ' . $data->period->end]);
            $writer->addRow([]);

            $writer->addRow(['ПОСТУПЛЕНИЯ']);
            $writer->addRow(['Показатель', 'Сумма']);
            $writer->addRow(['План', $data->incomes->plan_formatted]);
            $writer->addRow(['Факт', $data->incomes->fact_formatted]);
            $writer->addRow(['Исполнение, %', $data->incomes->execution_percent . '%']);
            $writer->addRow([]);

            $writer->addRow(['ПЛАТЕЖИ']);
            $writer->addRow(['Показатель', 'Сумма']);
            $writer->addRow(['План', $data->payments->plan_formatted]);
            $writer->addRow(['Факт', $data->payments->fact_formatted]);
            $writer->addRow(['Исполнение, %', $data->payments->execution_percent . '%']);
            $writer->addRow([]);

            $writer->addRow(['БАЛАНС']);
            $writer->addRow(['План', $data->incomes->plan - $data->payments->plan]);
            $writer->addRow(['Факт', $data->incomes->fact - $data->payments->fact]);
        };

        return response()->stream($callback, 200, $headers);
    }
}
