<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $env = config('app.env');
        $email = config('mail.username');

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

            $schedule->command('pos:generateRecurringExpense')->dailyAt('02:00');

        }

        if ($env === 'demo' && config('demo_reset.legacy_full_database_reset_enabled')) {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }

        if (config('demo_reset.enabled')) {
            foreach (config('demo_reset.business_ids', []) as $businessId) {
                $schedule->command('demo:restore '.(int) $businessId.' --force')
                    ->dailyAt(config('demo_reset.time', '01:00'))
                    ->timezone(config('demo_reset.timezone', 'Africa/Cairo'))
                    ->withoutOverlapping(120)
                    ->onOneServer();
            }
        }

        // Allows hosts without shell access to capture a new demo baseline by
        // temporarily setting DEMO_RESET_BOOTSTRAP_BUSINESS_IDS. The existing
        // cron invokes this once; after the snapshot exists no event is added.
        foreach (config('demo_reset.bootstrap_business_ids', []) as $businessId) {
            $businessId = (int) $businessId;
            $snapshot = rtrim(config('demo_reset.snapshot_directory'), DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR."business-{$businessId}.snapshot.gz";
            $dryRunOutput = rtrim(config('demo_reset.snapshot_directory'), DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR."business-{$businessId}.bootstrap-dry-run.txt";

            if ($businessId > 0 && ! File::exists($snapshot)) {
                $schedule->command('demo:snapshot '.$businessId)
                    ->everyMinute()
                    ->withoutOverlapping(120)
                    ->onOneServer();
            } elseif ($businessId > 0 && ! File::exists($dryRunOutput)) {
                $schedule->command('demo:restore '.$businessId.' --dry-run')
                    ->everyMinute()
                    ->withoutOverlapping(120)
                    ->onOneServer()
                    ->sendOutputTo($dryRunOutput);
            }
        }

    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
