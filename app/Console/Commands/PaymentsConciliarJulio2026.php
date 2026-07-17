<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación one-shot de julio 2026: ajustes puntuales de datos para que
 * los reportes de caja cuadren con el legacy en los días 13–17 (sobrantes
 * registrados como capital antes del deploy de la regla, y centavos donde el
 * legacy asentó el desglose distinto). Cada ajuste tiene guard por estado
 * actual: es idempotente y seguro de re-ejecutar.
 *
 * Casos que NO cubre (registrar a mano en el sistema):
 *   - Pago S/ 283.40 del crédito 28445 (16-07-2026) — falta en el sistema nuevo.
 *   - Gasto S/ 149.70 "Rpc Rosa, Guilmer, Ruben." Fijos/Mensual (16-07-2026).
 *
 * Uso:
 *   php artisan payments:conciliar-julio2026 --dry-run
 *   php artisan payments:conciliar-julio2026
 */
class PaymentsConciliarJulio2026 extends Command
{
    protected $signature = 'payments:conciliar-julio2026 {--dry-run : Muestra qué haría sin aplicar cambios}';

    protected $description = 'Ajustes puntuales de conciliación con el legacy para pagos del 13-17 de julio 2026 (idempotente).';

    private bool $dry = false;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        if ($this->dry) {
            $this->line('[DRY-RUN] Sin aplicar cambios.');
        }

        // 1-3) Sobrantes convertidos a interés de la cuota siguiente
        //    (el legacy los asentó como interés; op con capital, fuera del
        //    alcance de payments:cuadrar-sobrantes).
        $this->convertirCapitalAInteres(501099, 29128, 0.05, 'Pago : 29128 Interes:  6/72 Dias : 8');
        $this->convertirCapitalAInteres(501134, 29163, 5.00, 'Pago : 29163 Interes:  6/32 Dias : 3');
        $this->convertirCapitalAInteres(501205, 29246, 0.07, 'Pago : 29246 Interes:  2/48 Dias : 7');

        // 4) 29054 (14-jul): del INT 0.57 de la cuota 12, 0.14 corresponden a
        //    capital según el asiento del legacy (centavos de su cronograma).
        $this->partirInteres501131();

        // 5) 28942 (16-jul): sobrante 155 de la cuota 19 → 125 interés + 30
        //    capital (asiento del legacy). Funciona desde el estado crudo
        //    (CAP 155) o desde el split de cuadrar-sobrantes (INT 150 + CAP 5).
        $this->ajustar28942();

        $this->newLine();
        $this->info(($this->dry ? '[DRY-RUN] ' : '').'Conciliación julio 2026 terminada.');
        $this->line('Pendientes manuales: pago S/ 283.40 crédito 28445 (16-07) y gasto S/ 149.70 "Rpc Rosa, Guilmer, Ruben." (16-07).');

        return self::SUCCESS;
    }

    private function convertirCapitalAInteres(int $paymentId, int $creditId, float $monto, string $detalle): void
    {
        $p = DB::table('payments')->where('id', $paymentId)
            ->where('credit_id', $creditId)->where('tipo', 'CAPITAL')
            ->whereBetween('monto', [$monto - 0.001, $monto + 0.001])->first();

        if (! $p) {
            $this->line("  [{$paymentId}] crédito {$creditId}: ya aplicado o estado distinto, skip.");

            return;
        }

        $this->line("  [{$paymentId}] crédito {$creditId}: CAPITAL {$monto} → INTERES ({$detalle}).");
        if ($this->dry) {
            return;
        }

        DB::transaction(function () use ($p, $monto, $detalle) {
            DB::table('payments')->where('id', $p->id)->update([
                'tipo' => 'INTERES', 'documento' => 'INTERES', 'detalle' => $detalle,
            ]);
            DB::table('credit_installments')->where('id', $p->installment_id)->update([
                'importe_aplicado' => DB::raw('importe_aplicado - '.$monto),
                'interes_aplicado' => DB::raw('interes_aplicado + '.$monto),
            ]);
            DB::table('mass_deletion_details')->where('payment_id', $p->id)->update(['tipo' => 'I']);
        });
    }

    /** 501131: INT 0.57 (cuota 12 de 29054) → INT 0.43 + CAP 0.14. */
    private function partirInteres501131(): void
    {
        $p = DB::table('payments')->where('id', 501131)
            ->where('tipo', 'INTERES')->whereBetween('monto', [0.569, 0.571])->first();

        if (! $p) {
            $this->line('  [501131] crédito 29054: ya aplicado o estado distinto, skip.');

            return;
        }

        $this->line('  [501131] crédito 29054: INT 0.57 → INT 0.43 + CAP 0.14.');
        if ($this->dry) {
            return;
        }

        DB::transaction(function () use ($p) {
            DB::table('payments')->where('id', $p->id)->update(['monto' => 0.43]);
            $newId = DB::table('payments')->insertGetId([
                'credit_id' => $p->credit_id, 'installment_id' => $p->installment_id,
                'modo' => $p->modo, 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
                'fecha' => $p->fecha, 'hora' => $p->hora, 'monto' => 0.14,
                'moneda' => $p->moneda, 'detalle' => 'Pago : 29054 Cuota:  12/48',
                'asesor' => $p->asesor, 'usuario' => $p->usuario, 'user_id' => $p->user_id,
                'headquarter_id' => $p->headquarter_id,
                'latitud' => $p->latitud, 'longitud' => $p->longitud,
                'created_at' => $p->created_at, 'updated_at' => now(),
            ]);
            DB::table('credit_installments')->where('id', $p->installment_id)->update([
                'interes_aplicado' => DB::raw('interes_aplicado - 0.14'),
                'importe_aplicado' => DB::raw('importe_aplicado + 0.14'),
            ]);
            $det = DB::table('mass_deletion_details')->where('payment_id', $p->id)->first();
            if ($det) {
                DB::table('mass_deletion_details')->where('id', $det->id)->update(['amount' => 0.43]);
                DB::table('mass_deletion_details')->insert([
                    'mass_deletion_id' => $det->mass_deletion_id, 'installment_id' => $det->installment_id,
                    'payment_id' => $newId, 'amount' => 0.14, 'fecha' => $det->fecha,
                    'tipo' => 'C', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    /** 28942 (16-jul): dejar la operación en INT 25 (c18) + INT 125 + CAP 30 (c19). */
    private function ajustar28942(): void
    {
        $cap = DB::table('payments')->where('id', 501227)->where('tipo', 'CAPITAL')->first();
        if (! $cap) {
            $this->line('  [501227] crédito 28942: estado distinto, skip.');

            return;
        }

        $capMonto = round((float) $cap->monto, 2);

        if ($capMonto === 30.00) {
            $this->line('  [501227] crédito 28942: ya aplicado, skip.');

            return;
        }

        if ($capMonto === 155.00) {
            // Estado crudo: CAP 155 → CAP 30 + fila INT 125 nueva.
            $this->line('  [501227] crédito 28942: CAP 155 → INT 125 + CAP 30.');
            if ($this->dry) {
                return;
            }

            DB::transaction(function () use ($cap) {
                DB::table('payments')->where('id', $cap->id)->update(['monto' => 30.00]);
                $newId = DB::table('payments')->insertGetId([
                    'credit_id' => $cap->credit_id, 'installment_id' => $cap->installment_id,
                    'modo' => $cap->modo, 'tipo' => 'INTERES', 'documento' => 'INTERES',
                    'fecha' => $cap->fecha, 'hora' => $cap->hora, 'monto' => 125.00,
                    'moneda' => $cap->moneda, 'detalle' => 'Pago : 28942 Interes:  19/48 Dias : 6',
                    'asesor' => $cap->asesor, 'usuario' => $cap->usuario, 'user_id' => $cap->user_id,
                    'headquarter_id' => $cap->headquarter_id,
                    'latitud' => $cap->latitud, 'longitud' => $cap->longitud,
                    'created_at' => $cap->created_at, 'updated_at' => now(),
                ]);
                DB::table('credit_installments')->where('id', $cap->installment_id)->update([
                    'importe_aplicado' => DB::raw('importe_aplicado - 125'),
                    'interes_aplicado' => DB::raw('interes_aplicado + 125'),
                ]);
                $det = DB::table('mass_deletion_details')->where('payment_id', $cap->id)->first();
                if ($det) {
                    DB::table('mass_deletion_details')->where('id', $det->id)->update(['amount' => 30.00]);
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id' => $det->mass_deletion_id, 'installment_id' => $det->installment_id,
                        'payment_id' => $newId, 'amount' => 125.00, 'fecha' => $det->fecha,
                        'tipo' => 'I', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });

            return;
        }

        if ($capMonto === 5.00) {
            // Estado post cuadrar-sobrantes: INT 150 + CAP 5 → INT 125 + CAP 30.
            $int = DB::table('payments')->where('credit_id', 28942)->where('fecha', $cap->fecha)
                ->where('installment_id', $cap->installment_id)
                ->where('tipo', 'INTERES')->whereBetween('monto', [149.999, 150.001])->first();
            if (! $int) {
                $this->warn('  [501227] crédito 28942: CAP 5 sin INT 150 asociado, revisar a mano.');

                return;
            }

            $this->line('  [501227] crédito 28942: INT 150 + CAP 5 → INT 125 + CAP 30.');
            if ($this->dry) {
                return;
            }

            DB::transaction(function () use ($cap, $int) {
                DB::table('payments')->where('id', $int->id)->update(['monto' => 125.00]);
                DB::table('payments')->where('id', $cap->id)->update(['monto' => 30.00]);
                DB::table('credit_installments')->where('id', $cap->installment_id)->update([
                    'interes_aplicado' => DB::raw('interes_aplicado - 25'),
                    'importe_aplicado' => DB::raw('importe_aplicado + 25'),
                ]);
                DB::table('mass_deletion_details')->where('payment_id', $int->id)->update(['amount' => 125.00]);
                DB::table('mass_deletion_details')->where('payment_id', $cap->id)->update(['amount' => 30.00]);
            });

            return;
        }

        $this->warn("  [501227] crédito 28942: monto inesperado ({$capMonto}), revisar a mano.");
    }
}
