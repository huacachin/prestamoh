<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteNotarial extends Model
{
    protected $table = 'tramites_notariales';

    /** Días en el mismo estado intermedio a partir de los cuales el trámite se considera varado */
    public const DIAS_VARADO = 15;

    /**
     * Estados en el orden del flujo. no_firmo es una excepción: se puede
     * marcar desde en_notaria y se sale de ella reintentando (vuelve a
     * en_notaria) o archivando.
     */
    public const ESTADOS = [
        'firmado_oficina' => 'Firmado en oficina',
        'en_notaria' => 'En notaría',
        'firmado' => 'Firmado en notaría',
        'por_recoger' => 'Por recoger',
        'recogido' => 'Recogido',
        'archivado' => 'Archivado',
        'no_firmo' => 'No firmó',
    ];

    /** Estados que cuentan para la alerta de varados (los finales no) */
    public const ESTADOS_ABIERTOS = ['firmado_oficina', 'en_notaria', 'firmado', 'por_recoger', 'no_firmo'];

    /** Transiciones válidas por estado (el resto se rechaza en la UI) */
    public const TRANSICIONES = [
        'firmado_oficina' => ['en_notaria'],
        'en_notaria' => ['firmado', 'no_firmo'],
        'firmado' => ['por_recoger', 'recogido'],
        'por_recoger' => ['recogido'],
        'recogido' => ['archivado'],
        'archivado' => [],
        'no_firmo' => ['en_notaria', 'archivado'],
    ];

    public const TIPOS = [
        'contrato_sigm' => 'Contrato SIGM',
        'garantia_hipotecaria' => 'Garantía hipotecaria',
        'carta_notarial' => 'Carta notarial',
        'contrato_arrendamiento' => 'Contrato de arrendamiento',
        'declaracion_jurada' => 'Declaración jurada',
        'transferencia_vehicular' => 'Transferencia vehicular',
        'testimonio' => 'Testimonio',
        'otro' => 'Otro',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'tipo' => 'contrato_sigm',
        'estado' => 'firmado_oficina',
    ];

    protected $fillable = [
        'garantia_id', 'contrato_id', 'client_id', 'tipo', 'descripcion', 'notaria',
        'estado', 'estado_desde', 'fecha_ingreso_notaria', 'fecha_firma', 'fecha_recojo',
        'costo', 'expense_id', 'ubicacion_archivo', 'nota', 'responsable_id', 'requiere_revision',
    ];

    protected $casts = [
        'estado_desde' => 'date',
        'fecha_ingreso_notaria' => 'date',
        'fecha_firma' => 'date',
        'fecha_recojo' => 'date',
        'costo' => 'decimal:2',
        'requiere_revision' => 'boolean',
    ];

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Trámites abiertos varados más de $dias en su estado actual */
    public function scopeVarados(Builder $q, int $dias = self::DIAS_VARADO): Builder
    {
        return $q->whereIn('estado', self::ESTADOS_ABIERTOS)
            ->where('estado_desde', '<=', now()->subDays($dias)->toDateString());
    }

    public function diasEnEstado(): int
    {
        return (int) $this->estado_desde->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function puedeTransicionarA(string $nuevoEstado): bool
    {
        return in_array($nuevoEstado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    /**
     * Avanza el estado registrando estado_desde y el hito de fecha que
     * corresponda. La validación de la transición es del llamador (UI).
     */
    public function avanzarA(string $nuevoEstado, ?string $fecha = null): void
    {
        $fecha = $fecha ?: now()->toDateString();

        $hitos = match ($nuevoEstado) {
            'en_notaria' => ['fecha_ingreso_notaria' => $fecha],
            'firmado' => ['fecha_firma' => $fecha],
            'recogido' => ['fecha_recojo' => $fecha],
            default => [],
        };

        $this->update(array_merge($hitos, [
            'estado' => $nuevoEstado,
            'estado_desde' => $fecha,
        ]));
    }
}
