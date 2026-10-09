<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTA: "Capital T." (capital_neto) se calcula EN VIVO al abrir
// /reports/cash-statistics (self-heal del mes visto, sin cron). El comando
// `reports:snapshot-capital-neto` queda disponible solo para backfill manual.

// 08/10/2026: las capturas de pantalla de la auditoría se conservan
// config('auditoria.capturas.dias') días; el resto se borra de madrugada.
// Requiere el cron del scheduler en el droplet (`php artisan schedule:run`).
Schedule::command('auditoria:purgar-capturas')->dailyAt('03:30');
