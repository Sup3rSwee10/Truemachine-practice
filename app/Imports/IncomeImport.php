<?php

namespace App\Imports;

use App\Models\Income;
use App\Models\Counterparty;
use App\Models\Item;
use Spatie\SimpleExcel\SimpleExcelReader;
use Carbon\Carbon;

/**
 * Импорт поступлений
 * 
 * Ожидаемые колонки в Excel:
 * - контрагент (обязательно)
 * - сумма (обязательно) 
 * - дата (опционально)
 * - статья (опционально)
 * - название (опционально)
 */
class IncomeImport
{
    protected int $accountId;
    protected int $createdCount = 0;
    protected array $errors = [];

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
    }

    public function import(string $filePath): self
    {
        try {
            $rows = SimpleExcelReader::create($filePath)->getRows();

            foreach ($rows as $index => $row) {
                try {
                    $this->createIncome($row);
                } catch (\Exception $e) {
                    $this->errors[] = 'Строка ' . ($index + 1) . ': ' . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = 'Ошибка чтения файла: ' . $e->getMessage();
        }

        return $this;
    }

    protected function createIncome(array $row): void
    {
        if (empty($row['контрагент'])) {
            throw new \Exception('Не указан контрагент');
        }
        if (empty($row['сумма']) || floatval($row['сумма']) <= 0) {
            throw new \Exception('Сумма должна быть больше 0');
        }

        $counterparty = Counterparty::firstOrCreate(
            ['name' => trim($row['контрагент'])],
            ['inn' => $row['инн'] ?? null]
        );

        $itemName = trim($row['статья'] ?? 'Прочее');
        $item = Item::firstOrCreate(
            ['name' => $itemName],
            ['type' => 'income']
        );

        Income::create([
            'name' => $row['название'] ?? $row['контрагент'] . ' (' . $row['статья'] . ')',
            'amount' => (int) round(floatval($row['сумма']) * 100),
            'planned_date' => Carbon::parse($row['дата'] ?? now()),
            'account_id' => $this->accountId,
            'counterparty_id' => $counterparty->id,
            'item_id' => $item->id,
            'is_recurring' => false,
            'created_by' => null,
        ]);

        $this->createdCount++;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
