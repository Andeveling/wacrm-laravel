<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('api-keys:prune-audit')->daily();

// #64 — durably persisted webhook deliveries are pruned daily so the
// raw payload (which contains phone numbers + message text) doesn't
// outlive its operational need. 30 days default.
Schedule::command('whatsapp:prune-deliveries')->daily();
