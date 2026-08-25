<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PapeletaRecurso extends Model
{
    protected $table = 'papeleta_recursos';

    /** Días de anticipación con que un recurso pendiente entra a la campana legal */
    public const DIAS_AVISO = 3;

    public const TIPOS = [
        'acceso_informacion' => 'Acceso a la información',
        'descargo' => 'Descargo',
        'apelacion' => 'Apelación',
        'prescripcion' => 'Prescripción de PIT',
        'verificacion_datos' => 'Verificación de datos',
        'beneficio_prs' => 'Beneficio PRS',
        'caducidad' => 'Caducidad del PAS',
        'reconocimiento' => 'Reconocimiento de responsabilidad',
        'retiro_puntos' => 'Retiro de puntos (MTC)',
        'fraccionamiento' => 'Fraccionamiento',
        'otro' => 'Otro',
    ];

    /** Plazo legal en días para calcular plazo_vence al presentar (editable) */
    public const PLAZOS_DIAS = [
        'acceso_informacion' => 10,
        // el resto de recursos: 30 días
    ];

    public const PLAZO_DEFAULT_DIAS = 30;

    public const RESULTADOS = [
        'pendiente' => 'Pendiente',
        'fundado' => 'Fundado',
        'infundado' => 'Infundado',
        'improcedente' => 'Improcedente',
        'atendido' => 'Atendido',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'resultado' => 'pendiente',
    ];

    protected $fillable = [
        'papeleta_id', 'tipo', 'nro_tramite', 'fecha_presentacion', 'plazo_vence',
        'resultado', 'resuelto_at', 'nota', 'registrado_por',
    ];

    protected $casts = [
        'fecha_presentacion' => 'date',
        'plazo_vence' => 'date',
        'resuelto_at' => 'date',
    ];

    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Plazo legal del tipo: 10 días el acceso a la información, 30 el resto */
    public static function plazoDias(string $tipo): int
    {
        return self::PLAZOS_DIAS[$tipo] ?? self::PLAZO_DEFAULT_DIAS;
    }

    /** Pendientes que vencen dentro de $dias (o ya vencieron) — campana */
    public function scopePorVencer(Builder $q, int $dias = self::DIAS_AVISO): Builder
    {
        return $q->where('resultado', 'pendiente')
            ->whereNotNull('plazo_vence')
            ->where('plazo_vence', '<=', now()->addDays($dias)->toDateString());
    }
}
