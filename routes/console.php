<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTA: "Capital T." (capital_neto) se calcula EN VIVO al abrir
// /reports/cash-statistics (self-heal del mes visto, sin cron). El comando
// `reports:snapshot-capital-neto` queda disponible solo para backfill manual.
