<?php

namespace App\Exports;

use App\Models\Registry;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Экспорт реестра платежей 
 * 
 * Создает файл с данными реестра
 */
class RegistryExport
{

    public static function download(int $registryId)
    {
        $registry = Registry::with('payments.counterparty')->findOrFail($registryId);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="реестр_' . $registryId . '_' . date('Y-m-d') . '.xlsx"',
        ];

        $callback = function () use ($registry) {
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');

            $writer->addRow([
                'ID',
                'Контрагент',
                'Сумма (₽)',
                'Назначение',
                'Статус',
                'Дата',
                'Приоритет'
            ]);

            foreach ($registry->payments as $payment) {
                $writer->addRow([
                    $payment->id,
                    $payment->counterparty?->name ?? 'Без контрагента',
                    number_format($payment->amount / 100, 2, '.', ' '),
                    $payment->name ?? '',
                    $payment->status ?? '',
                    $payment->planned_date ?? '',
                    $payment->priority ?? '-',
                ]);
            }
        };

        return response()->stream($callback, 200, $headers);
    }
}
