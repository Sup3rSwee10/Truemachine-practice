<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\RecurringTemplate;
use App\Models\Payment;
use App\Models\Income;
use Carbon\Carbon;

/**
 * Команда для генерации повторяющихся платежей и поступлений из шаблонов
 * 
 * Запускается автоматически по расписанию 
 * или вручную с указанием конкретного шаблона
 */
class GenerateRecurringPayments extends Command
{
    protected $signature = 'payments:generate-recurring {--template-id=}';
    protected $description = 'Генерация заявок из шаблонов повторяющихся платежей и поступлений';

    public function handle(): int
    {
        $today = Carbon::today();
        $searchUntil = $today->copy();
        if ($today->isFriday()) {
            $searchUntil->addDays(2);
        }

        // Получаем активные шаблоны
        $query = RecurringTemplate::where('start_date', '<=', $searchUntil)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });

        // Если указан конкретный шаблон - генерируем только его
        if ($templateId = $this->option('template-id')) {
            $query->where('id', $templateId);
        }

        $templates = $query->get();
        $generatedCount = 0;

        // Генерируем заявки для каждого шаблона
        foreach ($templates as $template) {
            $generatedCount += $this->generateForTemplate($template, $searchUntil);
        }

        $this->info("Успешно сгенерировано заявок: {$generatedCount}");

        return 0;
    }

    public function generateForTemplate(RecurringTemplate $template, ?Carbon $searchUntil = null): int
    {
        // Если дата окончания не передана - берем сегодняшний день с учетом пятницы
        if (!$searchUntil) {
            $searchUntil = Carbon::today();
            if ($searchUntil->isFriday()) {
                $searchUntil->addDays(2);
            }
        }

        $generatedCount = 0;

        // Определяем дату следующей генерации
        $currentDate = $this->calculateNextScheduleDate($template);

        // Если дата не определена - начинаем с даты начала шаблона
        if (!$currentDate) {
            $currentDate = Carbon::parse($template->start_date);
        }

        $maxDate = $searchUntil->copy();

        // Генерируем заявки для всех дат до максимальной
        while ($currentDate && $currentDate->lessThanOrEqualTo($maxDate)) {
            $nextScheduleDate = $currentDate->copy();

            // Корректировка даты: если выпадает на выходной - переносим на пятницу
            $plannedDate = $nextScheduleDate->copy();
            if ($plannedDate->isSaturday()) {
                $plannedDate->subDay();
            } elseif ($plannedDate->isSunday()) {
                $plannedDate->subDays(2);
            }

            // Проверяем, не была ли уже создана заявка на эту дату
            $alreadyExists = $this->checkIfExists($template, $nextScheduleDate);

            // Если заявки еще нет - создаем
            if (!$alreadyExists) {
                $this->createEntry($template, $plannedDate, $nextScheduleDate, $generatedCount);
                $generatedCount++;
            }

            // Переходим к следующей дате по расписанию
            $currentDate = $this->getNextDate($currentDate, $template->frequency);
        }

        // Обновляем дату последней генерации в шаблоне
        if ($generatedCount > 0) {
            $lastDate = $this->getLastGeneratedDate($template);
            if ($lastDate) {
                $template->update(['last_generated_date' => $lastDate->format('Y-m-d')]);
            }
        }

        return $generatedCount;
    }

    private function checkIfExists(RecurringTemplate $template, Carbon $scheduleDate): bool
    {
        $date = $scheduleDate->format('Y-m-d');

        if ($template->type === 'payment') {
            return Payment::where('recurring_template_id', $template->id)
                ->where('original_planned_date', $date)
                ->exists();
        }

        return Income::where('recurring_template_id', $template->id)
            ->where('planned_date', $date)
            ->exists();
    }

    private function createEntry(RecurringTemplate $template, Carbon $plannedDate, Carbon $originalDate, int &$generatedCount): void
    {
        DB::transaction(function () use ($template, $plannedDate, $originalDate, &$generatedCount) {
            if ($template->type === 'payment') {
                Payment::create([
                    'name' => $template->name,
                    'amount' => $template->amount,
                    'planned_date' => $plannedDate->format('Y-m-d'),
                    'original_planned_date' => $originalDate->format('Y-m-d'),
                    'account_id' => $template->account_id,
                    'counterparty_id' => $template->counterparty_id,
                    'item_id' => $template->item_id,
                    'priority' => $template->priority ?? 'medium',
                    'status' => 'draft',
                    'is_recurring' => true,
                    'recurring_template_id' => $template->id,
                    'created_by' => null,
                ]);
            } else {
                Income::create([
                    'name' => $template->name,
                    'amount' => $template->amount,
                    'planned_date' => $plannedDate->format('Y-m-d'),
                    'account_id' => $template->account_id,
                    'counterparty_id' => $template->counterparty_id,
                    'item_id' => $template->item_id,
                    'is_recurring' => true,
                    'recurring_template_id' => $template->id,
                    'created_by' => null,
                ]);
            }

            $generatedCount++;
        });
    }

    private function getNextDate(Carbon $currentDate, string $frequency): ?Carbon
    {
        switch ($frequency) {
            case 'daily':
                return $currentDate->copy()->addDay();
            case 'weekly':
                return $currentDate->copy()->addWeek();
            case 'monthly':
                return $currentDate->copy()->addMonth();
            default:
                return null;
        }
    }

    private function getLastGeneratedDate(RecurringTemplate $template): ?Carbon
    {
        if ($template->type === 'payment') {
            $lastPayment = Payment::where('recurring_template_id', $template->id)
                ->orderBy('original_planned_date', 'desc')
                ->first();

            return $lastPayment ? Carbon::parse($lastPayment->original_planned_date) : null;
        }

        $lastIncome = Income::where('recurring_template_id', $template->id)
            ->orderBy('planned_date', 'desc')
            ->first();

        return $lastIncome ? Carbon::parse($lastIncome->planned_date) : null;
    }

    private function calculateNextScheduleDate(RecurringTemplate $template): ?Carbon
    {
        $lastGenerated = $template->last_generated_date
            ? Carbon::parse($template->last_generated_date)
            : null;

        $startDate = Carbon::parse($template->start_date);

        if (!$lastGenerated) {
            return $startDate;
        }

        switch ($template->frequency) {
            case 'daily':
                return $lastGenerated->copy()->addDay();
            case 'weekly':
                return $lastGenerated->copy()->addWeek();
            case 'monthly':
                return $lastGenerated->copy()->addMonth();
            default:
                return null;
        }
    }
}
