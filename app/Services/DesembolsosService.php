<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Credit;
use Illuminate\Support\Collection;

/**
 * Desembolsos (créditos ACTIVADOS) de un período: fuente ÚNICA que comparten
 * el héroe del dashboard y la vista /reports/desembolsos, para que el número
 * del chip y el listado al que enlaza cuadren SIEMPRE por construcción.
 *
 * Definición (confirmada con el negocio, 24/07/2026):
 *  - Activado = fecha_actualizacion dentro del período (sin filtrar por
 *    situación actual: un crédito activado y ya cancelado igual cuenta).
 *  - NUEVO = cod_rem <> 'REF' (o null) · REFINANCIADO = cod_rem = 'REF'.
 */
final class DesembolsosService
{
    /**
     * Totales del período, desglosados en nuevos y refinanciados.
     *
     * @return array{nuevos: array{n:int, total:float}, refis: array{n:int, total:float}}
     */
    public function resumen(string $desde, string $hasta): array
    {
        $r = Credit::query()
            ->whereBetween('fecha_actualizacion', [$desde, $hasta])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN 1 ELSE 0 END), 0) as refi_n,
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN importe ELSE 0 END), 0) as refi_total,
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN 0 ELSE 1 END), 0) as nuevo_n,
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN 0 ELSE importe END), 0) as nuevo_total
            ")
            ->first();

        return [
            'nuevos' => ['n' => (int) $r->nuevo_n, 'total' => (float) $r->nuevo_total],
            'refis' => ['n' => (int) $r->refi_n, 'total' => (float) $r->refi_total],
        ];
    }

    /**
     * Listado de créditos activados en el período.
     *
     * @param  string  $tipo  'todos' | 'nuevos' | 'refinanciados'
     */
    public function listado(string $desde, string $hasta, string $tipo = 'todos'): Collection
    {
        return Credit::query()
            ->with(['client:id,nombre,apellido_pat,apellido_mat,documento,expediente,asesor_id', 'client.asesor:id,name,username'])
            ->whereBetween('fecha_actualizacion', [$desde, $hasta])
            ->when($tipo === 'nuevos', fn ($q) => $q->where(function ($w) {
                $w->where('cod_rem', '<>', 'REF')->orWhereNull('cod_rem');
            }))
            ->when($tipo === 'refinanciados', fn ($q) => $q->where('cod_rem', 'REF'))
            ->orderBy('fecha_actualizacion')
            ->orderBy('id')
            ->get();
    }
}
