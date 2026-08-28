<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigmAviso extends Model
{
    protected $table = 'sigm_avisos';

    /** Tasa registral por aviso electrónico en el SIGM (S/) */
    public const TASA_SIGM = 4.00;

    /** Vigencia por defecto de un aviso de constitución/renovación (D. Leg. 1400: 5 años) */
    public const VIGENCIA_ANIOS_DEFAULT = 5;

    public const TIPOS = [
        'constitucion' => 'Constitución',
        'renovacion' => 'Renovación',
        'modificacion' => 'Modificación',
        'cancelacion' => 'Cancelación',
        'ejecucion' => 'Ejecución',
    ];

    public const ESTADOS = [
        'registrado' => 'Registrado',
        'observado' => 'Observado',
        'anulado' => 'Anulado',
    ];

    public const MODALIDADES_EJECUCION = [
        'venta' => 'Venta extrajudicial',
        'adjudicacion' => 'Adjudicación',
    ];

    /** Default espejo de la migración: el modelo recién creado lo ve sin refresh() */
    protected $attributes = [
        'estado' => 'registrado',
    ];

    protected $fillable = [
        'garantia_id', 'tipo', 'nro_formulario', 'folio', 'fecha_presentacion',
        'vigencia_hasta', 'modalidad_ejecucion', 'fecha_inicio_ejecucion',
        'fecha_termino_ejecucion', 'tasa', 'expense_id', 'estado', 'nota', 'registrado_por',
    ];

    protected $casts = [
        'fecha_presentacion' => 'date',
        'vigencia_hasta' => 'date',
        'fecha_inicio_ejecucion' => 'date',
        'fecha_termino_ejecucion' => 'date',
        'tasa' => 'decimal:2',
    ];

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
