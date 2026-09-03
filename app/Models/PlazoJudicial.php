<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlazoJudicial extends Model
{
    protected $table = 'plazos_judiciales';

    /** Días de anticipación con que un plazo pendiente entra a la campana legal */
    public const DIAS_AVISO = 2;

    protected $fillable = [
        'expediente_id', 'actuacion_id', 'descripcion', 'fecha_vencimiento',
        'cumplido_at', 'responsable_id',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cumplido_at' => 'datetime',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ExpedienteJudicial::class, 'expediente_id');
    }

    public function actuacion(): BelongsTo
    {
        return $this->belongsTo(ActuacionJudicial::class, 'actuacion_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Pendientes que vencen dentro de $dias (o ya vencieron) — campana */
    public function scopePorVencer(Builder $q, int $dias = self::DIAS_AVISO): Builder
    {
        return $q->whereNull('cumplido_at')
            ->where('fecha_vencimiento', '<=', now()->addDays($dias)->toDateString());
    }
}
