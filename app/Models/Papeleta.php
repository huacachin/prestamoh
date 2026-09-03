<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Papeleta extends Model
{
    protected $table = 'papeletas';

    public const ENTIDADES = [
        'SAT' => 'SAT Lima',
        'ATU' => 'ATU',
        'SAT_CALLAO' => 'SAT Callao',
        'SUTRAN' => 'SUTRAN',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_recurso' => 'En recurso',
        'fraccionada' => 'Fraccionada',
        'pagada' => 'Pagada',
        'anulada' => 'Anulada',
        'judicializada' => 'Judicializada',
    ];

    public const RESPONSABLES = [
        'propietario' => 'Propietario',
        'empresa' => 'Empresa',
        'prop_empresa' => 'Propietario-Empresa',
        'conductor' => 'Conductor',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'estado' => 'pendiente',
    ];

    protected $fillable = [
        'vehiculo_id', 'entidad', 'nro_papeleta', 'codigo_falta', 'puntos',
        'fecha_infraccion', 'monto', 'responsable_pago', 'conductor_nombre',
        'conductor_documento', 'estado', 'requiere_revision', 'nota', 'registrado_por',
    ];

    protected $casts = [
        'fecha_infraccion' => 'date',
        'monto' => 'decimal:2',
        'requiere_revision' => 'boolean',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(PapeletaRecurso::class)->orderByDesc('fecha_presentacion');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
