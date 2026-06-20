<?php

namespace App\Exports;

use App\Livewire\Payments\Monthly;

/**
 * Excel del reporte de pagos MENSUAL (/payments/monthly) — réplica de pagosmes.php.
 */
class PaymentsMonthlyExport extends PaymentsPeriodExport
{
    protected function componentClass(): string
    {
        return Monthly::class;
    }

    protected function reportTitle(): string
    {
        return 'REPORTE CREDITO MENSUAL';
    }
}
