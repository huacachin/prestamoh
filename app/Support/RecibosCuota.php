<?php

namespace App\Support;

use App\Models\Credit;
use App\Models\ShortLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Último cobro (mass_deletion) que tocó cada cuota + links del recibo para
 * la columna "Rec." del cronograma:
 *  - 'ver': link PÚBLICO firmado del recibo (URL::signedRoute, permanente —
 *    el cliente lo abre sin login; un id alterado da 403).
 *  - 'pdf': descarga del recibo en PDF (misma firma).
 *  - 'wa': WhatsApp al celular1 del cliente con el link corto del recibo.
 * Cuotas sin cobro asociado no aparecen (la columna va vacía).
 *
 * Compartido por /payments/create y /credits/{id}/schedule.
 */
class RecibosCuota
{
    /** @return array<int, array{wa: ?string, pdf: string, ver: string}> por installment_id */
    public static function porCuota(Credit $credit): array
    {
        $ultimoPorCuota = DB::table('mass_deletion_details')
            ->join('mass_deletions', 'mass_deletions.id', '=', 'mass_deletion_details.mass_deletion_id')
            ->where('mass_deletions.credit_id', $credit->id)
            ->whereNotNull('mass_deletion_details.installment_id')
            ->groupBy('mass_deletion_details.installment_id')
            ->selectRaw('mass_deletion_details.installment_id, MAX(mass_deletion_details.mass_deletion_id) as md_id')
            ->pluck('md_id', 'installment_id');

        if ($ultimoPorCuota->isEmpty()) {
            return [];
        }

        $cobros = DB::table('mass_deletions')
            ->whereIn('id', $ultimoPorCuota->unique())
            ->get(['id', 'amount', 'date'])
            ->keyBy('id');

        $tel = preg_replace('/\D/', '', (string) $credit->client?->celular1);
        $empresa = (string) config('printer.company_name', 'HUACACHIN');

        $out = [];
        foreach ($ultimoPorCuota as $insId => $mdId) {
            $cobro = $cobros->get($mdId);
            if (! $cobro) {
                continue;
            }

            $ver = URL::signedRoute('recibo.publico', ['massDeletionId' => $mdId]);
            $pdf = URL::signedRoute('recibo.pdf', ['massDeletionId' => $mdId]);

            $wa = null;
            if ($tel !== '') {
                $msg = $empresa.': su recibo de pago #'.str_pad((string) $mdId, 6, '0', STR_PAD_LEFT)
                    .' del '.Carbon::parse($cobro->date)->format('d/m/Y')
                    .' por S/ '.number_format((float) $cobro->amount, 2)
                    // Link corto (/s/{code}): la URL firmada mide ~130 caracteres
                    // y dentro del mensaje espanta; el corto es idempotente por
                    // recibo, así que la tabla no crece con cada render.
                    .'. Vealo aqui: '.ShortLink::para($ver);
                $wa = 'https://api.whatsapp.com/send?phone=51'.$tel.'&text='.rawurlencode($msg);
            }

            $out[(int) $insId] = ['wa' => $wa, 'pdf' => $pdf, 'ver' => $ver];
        }

        return $out;
    }
}
