<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     * Laravel will auto-load any PHP file inside app/Console/Commands.
     */
    protected function commands(): void
    {
        // Auto-load all commands in app/Console/Commands
        $this->load(__DIR__.'/Commands');

        // If you keep a routes/console.php file, you can include it:
        // require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Example (disabled): run lot price backfill daily at 01:00
        // $schedule->command('pharma:backfill-lot-prices --only-null')->dailyAt('01:00');

        // ✅ Your weekly seeder at 02:30 every Monday
        $schedule->command('db:seed', [
            '--class'          => \Database\Seeders\PharmaSmartThresholdSeeder::class,
            '--no-interaction' => true,
        ])->weeklyOn(1, '02:30');

        // (Optional) Run article thresholds backfill weekly as well
        // $schedule->command('pharma:backfill-article-thresholds --min=1 --max=10')->weeklyOn(1, '02:40');
    }
}
