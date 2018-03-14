<?php

namespace App\Console;

/**
 * @author  Raqibul Hasan Moon <rhmoon21@gmail.com>
 * @created 2026-07-23
 */

use App\Console\Commands\SyncWorkerTraceSteps;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SyncWorkerTraceSteps::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Materialise the worker-side trace steps that the compiled
        // OrderSystemWorker JAR cannot write itself. The look-back window is
        // deliberately wider than the interval so a missed run still catches up.
        $schedule->command('trace:sync-worker --minutes=30')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }
}
