<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill post-migración legacy:
 *
 * El legacy `huaca_det_cuentacorriente.fechapago` representa el CRONOGRAMA de
 * vencimiento de cada cuota (NO la fecha en que se pagó realmente). La migración
 * vieja lo mapeó a `credit_installments.fecha_pago` y dejó `fecha_vencimiento`
 * en `now()` para todas (porque construía la fecha desde campos ano/mes/dia que
 * son códigos secuenciales legacy, no fechas reales).
 *
 * Este comando corrige esa inversión:
 *  1) Mueve el cronograma a su columna semántica: fecha_vencimiento ← fecha_pago.
 *  2) Limpia fecha_pago en cuotas no pagadas (debe ser NULL hasta que se pague).
 *
 * Cuotas ya pagadas: dejamos fecha_pago tal cual (= cronograma legacy, aproximación
 * razonable cuando no tenemos la fecha real del pago — el legacy mismo no la
 * distinguía a nivel cuota).
 */
class InstallationFixInstallmentDates extends Command
{
    protected $signature = 'installation:fix-installment-dates {--dry-run : Solo muestra qué cambiaría}';
    protected $description = 'Backfill credit_installments: fecha_vencimiento ← fecha_pago + NULL en no pagadas.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $aMover = DB::table('credit_installments')
            ->whereNotNull('fecha_pago')
            ->whereColumn('fecha_vencimiento', '<>', 'fecha_pago')
            ->count();

        $aNulificar = DB::table('credit_installments')
            ->where('pagado', 0)
            ->whereNotNull('fecha_pago')
            ->count();

        $this->table(['Acción', 'Filas afectadas'], [
            ['fecha_vencimiento ← fecha_pago (donde difieren)', number_format($aMover)],
            ['fecha_pago → NULL (cuotas no pagadas)',           number_format($aNulificar)],
        ]);

        if ($aMover === 0 && $aNulificar === 0) {
            $this->info('✓ Datos ya están consistentes. Nada que hacer.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no aplicado.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Aplicar los UPDATE en transacción?', true)) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            DB::statement("
                UPDATE credit_installments
                   SET fecha_vencimiento = fecha_pago
                 WHERE fecha_pago IS NOT NULL
                   AND fecha_vencimiento <> fecha_pago
            ");
            DB::statement("
                UPDATE credit_installments
                   SET fecha_pago = NULL
                 WHERE pagado = 0
                   AND fecha_pago IS NOT NULL
            ");
        });

        $this->info('✓ Backfill aplicado.');
        return self::SUCCESS;
    }
}
