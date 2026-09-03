<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActuacionJudicial extends Model
{
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
}
