<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reclasifica los sobrantes de pago que quedaron como CAPITAL de la cuota
 * siguiente cuando la operación entró en fase de interés (el capital de la
 * cuota en curso ya estaba cubierto). Según el flujo de negocio esos soles
 * son INTERÉS de la cuota siguiente, y así el desglose de Caja 1 cuadra con
 * el legacy (p. ej. crédito 29097 el 13-jul: pago 180 = 94.73 int cuota 9 +
 * 85.27 int cuota 10).
 *
 * Detección (misma operación = crédito + fecha + hora):
 *   - Fila CAPITAL con installment_id (pago del sistema nuevo).
 *   - La operación tiene fila INTERES en una cuota anterior.
 *   - La operación NO tiene fila CAPITAL en una cuota anterior (fase interés).
 *
 * Por cada fila detectada:
 *   - payments: tipo/documento CAPITAL → INTERES, detalle "Interes: N/M Dias: D".
 *   - credit_installments: mueve el monto de importe_aplicado a interes_aplicado.
 *   - mass_deletion_details: tipo C → I (para que Eliminar Masivo revierta bien).
 *
 * Se omite (con aviso) si el interés de la cuota destino no alcanza para
 * absorber el monto. Idempotente: las filas convertidas ya no son CAPITAL.
 *
 * Uso:
 *   php artisan payments:cuadrar-sobrantes --dry-run
 *   php artisan payments:cuadrar-sobrantes
 *   php artisan payments:cuadrar-sobrantes --desde=2026-07-15
 */
class PaymentsCuadrarSobrantes extends Command
{
    protected $signature = 'payments:cuadrar-sobrantes
        {--desde=2026-07-13 : Solo pagos desde esta fecha (go-live del sistema nuevo)}
        {--hasta= : Solo pagos hasta esta fecha (opcional)}
        {--dry-run : Muestra qué haría sin aplicar cambios}';

    protected $description = 'Reclasifica sobrantes de pagos en fase interés: CAPITAL de la cuota siguiente → INTERES (cuadra Caja 1 con el legacy).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $desde = (string) $this->option('desde');
        $hasta = $this->option('hasta');

        $q = DB::table('payments as p')
            ->join('credit_installments as i', 'i.id', '=', 'p.installment_id')
            ->join('credits as c', 'c.id', '=', 'p.credit_id')
            ->where('p.tipo', 'CAPITAL')
            ->where('p.fecha', '>=', $desde)
            // La misma operación pagó INTERES de una cuota anterior…
            ->whereExists(function ($s) {
                $s->from('payments as x')
                    ->join('credit_installments as xi', 'xi.id', '=', 'x.installment_id')
                    ->whereColumn('x.credit_id', 'p.credit_id')
                    ->whereColumn('x.fecha', 'p.fecha')
                    ->whereColumn('x.hora', 'p.hora')
                    ->where('x.tipo', 'INTERES')
                    ->whereColumn('xi.num_cuota', '<', 'i.num_cuota');
            })
            // …y NO pagó CAPITAL de una cuota anterior (entró en fase interés).
            ->whereNotExists(function ($s) {
                $s->from('payments as y')
                    ->join('credit_installments as yi', 'yi.id', '=', 'y.installment_id')
                    ->whereColumn('y.credit_id', 'p.credit_id')
                    ->whereColumn('y.fecha', 'p.fecha')
                    ->whereColumn('y.hora', 'p.hora')
                    ->where('y.tipo', 'CAPITAL')
                    ->whereColumn('yi.num_cuota', '<', 'i.num_cuota');
            })
            ->orderBy('p.id');

        if ($hasta) {
            $q->where('p.fecha', '<=', $hasta);
        }

        $rows = $q->get([
            'p.id', 'p.credit_id', 'p.fecha', 'p.monto',
            'i.id as inst_id', 'i.num_cuota', 'i.fecha_vencimiento',
            'i.importe_interes', 'i.interes_aplicado', 'i.importe_aplicado',
            'c.cuotas as tot_cuotas',
        ]);

        $convertidos = 0;
        $omitidos = 0;
        $headerImpreso = false;

        foreach ($rows as $r) {
            $intRestante = round((float) $r->importe_interes - (float) $r->interes_aplicado, 2);
            $etiqueta = "pago {$r->id} crédito {$r->credit_id} {$r->fecha} S/ {$r->monto} → Interes: {$r->num_cuota}/{$r->tot_cuotas}";

            // Interés de la cuota ya completo: el capital que siguió después
            // del interés es legítimo (pago que avanza cuotas), nada que hacer.
            if ($intRestante <= 0.001) {
                continue;
            }

            if (! $headerImpreso) {
                $this->line(($dry ? '[DRY-RUN] ' : '').'Sobrantes en fase interés registrados como CAPITAL:');
                $headerImpreso = true;
            }

            if ((float) $r->monto > (float) $r->importe_aplicado + 0.001) {
                $this->warn("  OMITIDO {$etiqueta}: capital aplicado ({$r->importe_aplicado}) menor al monto, revisar a mano.");
                $omitidos++;

                continue;
            }

            // Si el monto excede el interés restante de la cuota, se PARTE:
            // el interés restante pasa a fila INTERES y el resto queda como
            // CAPITAL legítimo (misma operación).
            $aInteres = round(min((float) $r->monto, $intRestante), 2);
            $quedaCapital = round((float) $r->monto - $aInteres, 2);

            $dias = $r->fecha_vencimiento
                ? abs((int) Carbon::parse($r->fecha_vencimiento)->diffInDays(Carbon::parse($r->fecha), false))
                : 0;
            $detalle = "Pago : {$r->credit_id} Interes:  {$r->num_cuota}/{$r->tot_cuotas} Dias : {$dias}";

            $this->line("  {$etiqueta}".($quedaCapital > 0 ? " [SPLIT: {$aInteres} int + {$quedaCapital} cap]" : '')." (Dias {$dias})");
            $convertidos++;

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($r, $detalle, $aInteres, $quedaCapital) {
                if ($quedaCapital > 0) {
                    // SPLIT: la fila CAPITAL conserva el resto y se crea una
                    // fila INTERES nueva por el interés restante de la cuota.
                    DB::table('payments')->where('id', $r->id)->where('tipo', 'CAPITAL')
                        ->update(['monto' => $quedaCapital]);
                    $orig = DB::table('payments')->where('id', $r->id)->first();
                    $newId = DB::table('payments')->insertGetId([
                        'credit_id' => $orig->credit_id, 'installment_id' => $orig->installment_id,
                        'modo' => $orig->modo, 'tipo' => 'INTERES', 'documento' => 'INTERES',
                        'fecha' => $orig->fecha, 'hora' => $orig->hora, 'monto' => $aInteres,
                        'moneda' => $orig->moneda, 'detalle' => $detalle, 'asesor' => $orig->asesor,
                        'usuario' => $orig->usuario, 'user_id' => $orig->user_id,
                        'headquarter_id' => $orig->headquarter_id,
                        'latitud' => $orig->latitud, 'longitud' => $orig->longitud,
                        'created_at' => $orig->created_at, 'updated_at' => now(),
                    ]);
                    // Rastro de eliminar masivo: la fila C baja al resto y se
                    // agrega una fila I por el interés, en la misma cabecera.
                    $det = DB::table('mass_deletion_details')->where('payment_id', $r->id)->first();
                    if ($det) {
                        DB::table('mass_deletion_details')->where('id', $det->id)->update(['amount' => $quedaCapital]);
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $det->mass_deletion_id, 'installment_id' => $det->installment_id,
                            'payment_id' => $newId, 'amount' => $aInteres, 'fecha' => $det->fecha,
                            'tipo' => 'I', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                } else {
                    // Conversión completa de la fila
                    DB::table('payments')->where('id', $r->id)->where('tipo', 'CAPITAL')->update([
                        'tipo' => 'INTERES', 'documento' => 'INTERES', 'detalle' => $detalle,
                    ]);
                    DB::table('mass_deletion_details')->where('payment_id', $r->id)->update(['tipo' => 'I']);
                }

                DB::table('credit_installments')->where('id', $r->inst_id)->update([
                    'importe_aplicado' => DB::raw('importe_aplicado - '.$aInteres),
                    'interes_aplicado' => DB::raw('interes_aplicado + '.$aInteres),
                ]);
            });
        }

        if ($convertidos === 0 && $omitidos === 0) {
            $this->info('Sin sobrantes por cuadrar: todo consistente.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] Se convertirían ' : 'Convertidos ').$convertidos.' pago(s)'.($omitidos ? ", {$omitidos} omitido(s) (revisar a mano)" : '').'.');

        return self::SUCCESS;
    }
}
