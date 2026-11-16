<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // The activity log grows forever otherwise — nothing was pruning
        // it. How long is kept is config/activitylog.php.
        $schedule->command('activitylog:clean')->daily();

        // A mailing scheduled for a time goes out at about that time.
        // Every minute, and overlapping runs cannot double-send — see the
        // command, which claims each mailing before starting it.
        $schedule->command('mailings:send-due')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
