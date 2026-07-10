<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cuota uniforme redondeada a 0.10 (créditos nuevos).
 *
 * Post-proceso sobre el cronograma recién generado (lógica legacy intacta):
 * redistribuye capital e interés al céntimo entre las cuotas cobrables y fija
 * una única cuota para todas = ceil(max(capital+interés) a 0.10). El delta de
 * cada cuota se guarda en importe_excedente (ingreso EXCEDENTE al cobrarse).
 *
 * Así desaparece la cuota final "fea" (ajuste de centavos) y todas las cuotas
 * salen idénticas; capital e interés siguen sumando exacto.
 */
class CuotaUniforme
{
    private const PASO = 0.10;

    /**
     * @param  float  $capitalTotal  capital exacto a repartir (importe del crédito)
     * @param  float  $interesTotal  interés total exacto del crédito
     */
    public static function aplicar(int $creditId, float $capitalTotal, float $interesTotal): void
    {
        // Solo cuotas cobrables (diario inserta filas 0/0 en fines de semana)
        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->where(fn ($q) => $q->where('importe_cuota', '>', 0)->orWhere('importe_interes', '>', 0))
            ->orderBy('num_cuota')->orderBy('id')
            ->get(['id']);

        $n = $cuotas->count();
        if ($n === 0) {
            return;
        }

        $caps = self::repartir($capitalTotal, $n);
        $ints = self::repartir($interesTotal, $n);

        // Cuota uniforme: la bruta más alta redondeada hacia arriba a 0.10
        $maxBruta = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $maxBruta = max($maxBruta, $caps[$i] + $ints[$i]);
        }
        $cuota = ceil(round($maxBruta / self::PASO, 6)) * self::PASO;

        foreach ($cuotas as $i => $row) {
            DB::table('credit_installments')->where('id', $row->id)->update([
                'importe_cuota' => $caps[$i],
                'importe_interes' => $ints[$i],
                'importe_excedente' => round($cuota - $caps[$i] - $ints[$i], 2),
            ]);
        }

        // interes_total del crédito = interés exacto repartido
        DB::table('credits')->where('id', $creditId)->update([
            'interes_total' => round($interesTotal, 2),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reparte $total en $n partes de 2 decimales que suman exacto: base
     * truncada + los céntimos sobrantes de a 0.01 en las primeras cuotas.
     *
     * @return list<float>
     */
    private static function repartir(float $total, int $n): array
    {
        $totalCent = (int) round($total * 100);
        $base = intdiv($totalCent, $n);
        $resto = $totalCent - ($base * $n);

        $partes = [];
        for ($i = 0; $i < $n; $i++) {
            $partes[] = ($base + ($i < $resto ? 1 : 0)) / 100;
        }

        return $partes;
    }
}
