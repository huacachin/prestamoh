<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Morosidad por cliente — LA fuente única del cálculo que usan los chips de
 * /clients (al día / 2 / 3 / 4+ / ejecución) y el Excel de clientes. Antes el
 * export no la conocía y el archivo salía sin el filtro de la pantalla; con
 * el helper compartido, ambos cuadran por construcción.
 *
 * Reglas (las de siempre, movidas tal cual desde Clients\Index):
 *  - Por cliente se toma el PEOR de sus créditos activos (cuotas vencidas
 *    impagas a hoy): 2 → naranja, 3 → rojo, 4+ → crítico; menos de 2 → al día.
 *  - "En ejecución" (anotado a mano en `zona`, ej. "SIGM.S-Ejecucion 08/04")
 *    sale del recuento de mora y tiene nivel propio.
 */
final class MorosidadClientes
{
    /**
     * @param  array<int, int>  $clientIds
     * @return array{morosidad: array<int, int>, enEjecucion: array<int, true>}
     */
    public static function calcular(array $clientIds): array
    {
        if ($clientIds === []) {
            return ['morosidad' => [], 'enEjecucion' => []];
        }

        $morosidad = [];
        $rows = DB::table('credits as c')
            ->join('credit_installments as i', 'i.credit_id', '=', 'c.id')
            ->whereIn('c.client_id', $clientIds)
            ->where('c.situacion', 'Activo')
            ->where('i.pagado', 0)
            ->whereNotNull('i.fecha_vencimiento')
            ->where('i.fecha_vencimiento', '<', now()->format('Y-m-d'))
            ->groupBy('c.id', 'c.client_id')
            ->selectRaw('c.client_id, COUNT(*) as vencidas')
            ->get();
        foreach ($rows as $r) {
            $morosidad[$r->client_id] = max($morosidad[$r->client_id] ?? 0, (int) $r->vencidas);
        }

        $enEjecucion = array_flip(
            Client::whereIn('id', $clientIds)
                ->where('zona', 'like', '%ejecuc%')
                ->pluck('id')
                ->all()
        );

        return ['morosidad' => $morosidad, 'enEjecucion' => $enEjecucion];
    }

    /**
     * IDs del conjunto que caen en el nivel pedido
     * ('aldia' | 'naranja' | 'rojo' | 'critico' | 'masde8' | 'ejecucion').
     * critico = 4 a 7 vencidas; masde8 = 8 o más (niveles excluyentes).
     *
     * @param  array<int, int>  $clientIds
     * @param  array<int, int>  $morosidad
     * @param  array<int, true>  $enEjecucion
     * @return array<int, int>
     */
    public static function idsDelNivel(array $clientIds, string $nivel, array $morosidad, array $enEjecucion): array
    {
        return array_values(array_filter($clientIds, function ($id) use ($nivel, $morosidad, $enEjecucion) {
            $v = $morosidad[$id] ?? 0;
            $ejecucion = isset($enEjecucion[$id]);

            return match ($nivel) {
                'ejecucion' => $ejecucion,
                'masde8' => $v >= 8 && ! $ejecucion,
                'critico' => $v >= 4 && $v <= 7 && ! $ejecucion,
                'rojo' => $v === 3 && ! $ejecucion,
                'naranja' => $v === 2 && ! $ejecucion,
                default => $v < 2 && ! $ejecucion, // aldia
            };
        }));
    }
}
