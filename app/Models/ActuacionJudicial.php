<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActuacionJudicial extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Actuación judicial';

    protected $table = 'actuaciones_judiciales';

    public const TIPOS = [
        'resolucion' => 'Resolución',
        'escrito_demandante' => 'Escrito (demandante)',
        'escrito_demandado' => 'Escrito (demandado)',
        'notificacion' => 'Notificación',
        'oficio' => 'Oficio',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'expediente_id', 'tipo', 'numero', 'fecha', 'sumilla', 'detalle', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ExpedienteJudicial::class, 'expediente_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return Str::limit((string) $this->sumilla, 60);
    }
}
