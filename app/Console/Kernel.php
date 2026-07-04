<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Ядро консольных команд 
 * 
 * Определяет расписание выполнения команд и регистрирует консольные команды
 */
class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule): void
    {

        $schedule->command('payments:generate-recurring')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/recurring_payments.log'));

        $schedule->command('rates:update')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/rates_update.log'));

        $schedule->command('rates:update')
            ->dailyAt('15:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/rates_update.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
