<?php

namespace App\Livewire\Cash\Concerns;

use App\Models\Income;

/**
 * Los motivos de los ingresos "Otros" para la lista con buscador del campo "A" (27/11/2026, Antony: "que
 * tenga la opción de poder agregar y búsquedas, lo mismo que cuando agregas un vehículo con color"; y el
 * mismo día: "también debe de ir en el editar"). Compartido entre CreateIncome y EditIncome; el componente
 * guarda el motivo en `$reason`.
 */
trait ConMotivosDeIngreso
{
    /**
     * Hasta cuántos meses atrás se ofrecen los motivos ya usados en "Otros". Los del sistema anterior
     * (2017–2020: placas, nombres sueltos, el mismo motivo escrito de cinco formas) no sirven de lista.
     */
    private const MESES_DE_MOTIVOS = 24;

    /**
     * Motivos ya usados en los ingresos "Otros" de la caja operativa. Del usado más recientemente al más
     * antiguo, sin repetir (sin mirar mayúsculas ni espacios) y solo los de los últimos dos años. Uno nuevo
     * se escribe en el mismo cuadro; al guardar el ingreso queda en la lista para la próxima vez. Lo que
     * está escrito va siempre en la lista, para que el cuadro lo conserve al redibujarse.
     *
     * @return list<string>
     */
    public function motivosDeOtros(): array
    {
        $motivos = $this->motivosUsados();
        $escrito = trim($this->reason);
        if ($escrito !== '' && ! isset($motivos[self::claveDeMotivo($escrito)])) {
            $motivos = [self::claveDeMotivo($escrito) => $escrito] + $motivos;
        }

        return array_values($motivos);
    }

    /**
     * Los motivos de "Otros" usados en los últimos dos años, del más reciente al más antiguo.
     *
     * @param  int|null  $salvoIngreso  ingreso que no cuenta como uso (el que se está editando)
     * @return array<string, string> clave sin mayúsculas ni espacios de más => como se escribió
     */
    private function motivosUsados(?int $salvoIngreso = null): array
    {
        // Sin GROUP BY: así manda la escritura del uso más reciente ("I.D" y "i.d " son el mismo motivo).
        $usados = Income::query()->where('caja', 1)->where('modo', 'Otros')
            ->where('date', '>=', now()->subMonths(self::MESES_DE_MOTIVOS)->toDateString())
            ->when($salvoIngreso !== null, fn ($q) => $q->whereKeyNot($salvoIngreso))
            ->orderByDesc('date')->orderByDesc('id')
            ->pluck('reason');

        $motivos = [];
        foreach ($usados as $motivo) {
            $motivo = trim((string) $motivo);
            if ($motivo !== '') {
                $motivos[self::claveDeMotivo($motivo)] ??= $motivo;
            }
        }

        return $motivos;
    }

    /**
     * El motivo tal como ya está en la lista si es el mismo salvo mayúsculas o espacios; si no, limpio de
     * espacios sobrantes. Al editar, el propio ingreso no cuenta: si es el único que usa ese motivo, se le
     * puede corregir la escritura ("banco" → "Banco").
     */
    private function motivoComoYaSeUso(string $motivo, ?int $salvoIngreso = null): string
    {
        $motivo = trim((string) preg_replace('/\s+/u', ' ', $motivo));

        return $this->motivosUsados($salvoIngreso)[self::claveDeMotivo($motivo)] ?? $motivo;
    }

    private static function claveDeMotivo(string $motivo): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $motivo)));
    }
}
