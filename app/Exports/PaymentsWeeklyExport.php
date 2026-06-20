<?php

namespace App\Exports;

use App\Livewire\Payments\Weekly;

/**
 * Excel del reporte de pagos SEMANAL (/payments/weekly) — réplica de pagossemanas.php.
 */
class PaymentsWeeklyExport extends PaymentsPeriodExport
{
    protected function componentClass(): string
    {
        return Weekly::class;
    }

    protected function reportTitle(): string
    {
        return 'REPORTE CREDITO SEMANAL';
    }
}
