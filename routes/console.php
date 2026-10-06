<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tenant-invitations:expire')->hourly()->withoutOverlapping();
Schedule::command('system:heartbeat')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('subscriptions:normalize')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:snapshot-usage')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('whatsapp-sessions:health')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-sessions:restore --limit=200')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-messages:expire')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:prepare-due')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:reconcile-preparations')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:launch-due')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:dispatch-due-retries')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:reconcile-executions')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('whatsapp-campaigns:reconcile-transport')->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('automations:reconcile-executions')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('integrations:reconcile-webhooks --limit=100')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
