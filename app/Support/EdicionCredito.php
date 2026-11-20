<?php

namespace App\Support;

use App\Models\Credit;
use App\Models\DocumentoCliente;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * ¿Se puede editar este préstamo? Reúne las reglas de la pantalla de edición (/credits/{id}/edit) para
 * que la pantalla y los botones que llevan a ella digan lo mismo:
 *
 *  1. El analista (scope propio) no edita préstamos.
 *  2. Solo se editan los registrados HOY, salvo quien tiene `caja.editar-historico` (mapa del 21/08).
 *  3. 20/11/2026 (Antony, tras hablar con el cliente: "la opción de editar el préstamo solo se pueda
 *     hacer hasta que todos los pasos de ese préstamo se hayan completado, luego que se bloquee"): con el
 *     CONTRATO EMITIDO el préstamo ya no se edita, tampoco el director ni contabilidad — sus montos ya
 *     están en un documento firmado. Para corregirlo se anula antes el contrato (que tiene su propia
 *     regla) y vuelve a quedar editable. Se suma a la regla 2, no la reemplaza; los préstamos que no
 *     llevan contrato siguen como antes.
 */
final class EdicionCredito
{
    public const MOTIVO_ROL = 'Tu rol no permite esta acción.';

    public const MOTIVO_DIA = 'Solo se pueden editar créditos registrados hoy.';

    public const MOTIVO_CONTRATO = 'Este préstamo ya tiene el contrato emitido y no se puede editar. Para corregirlo, anula antes el contrato en Documentos.';

    /**
     * Por qué el usuario no puede editar el préstamo, o null si puede.
     *
     * @param  ?bool  $conContrato  si ya se sabe (listados, ver conContrato()); null = se consulta
     */
    public static function motivoBloqueo(?Authorizable $user, Credit $credit, ?bool $conContrato = null): ?string
    {
        if ($user?->can('clientes.scope-propio') ?? false) {
            return self::MOTIVO_ROL;
        }
        $registradoHoy = $credit->fecha_prestamo?->format('Y-m-d') === now()->format('Y-m-d');
        if (! ($user?->can('caja.editar-historico') ?? false) && ! $registradoHoy) {
            return self::MOTIVO_DIA;
        }

        return ($conContrato ?? self::tieneContrato($credit)) ? self::MOTIVO_CONTRATO : null;
    }

    /** ¿El préstamo tiene un contrato emitido (no anulado)? */
    public static function tieneContrato(Credit|int $credit): bool
    {
        return self::conContrato([$credit instanceof Credit ? (int) $credit->id : $credit]) !== [];
    }

    /**
     * De estos préstamos, los que tienen contrato emitido (una consulta, para listados).
     *
     * @param  iterable<int>  $creditIds
     * @return list<int>
     */
    public static function conContrato(iterable $creditIds): array
    {
        $ids = array_values(array_unique(array_map('intval', is_array($creditIds) ? $creditIds : iterator_to_array($creditIds, false))));
        if ($ids === []) {
            return [];
        }

        return DocumentoCliente::whereIn('credit_id', $ids)->where('tipo', 'contrato')->where('estado', 'emitido')
            ->distinct()->pluck('credit_id')->map(fn ($id) => (int) $id)->all();
    }
}
