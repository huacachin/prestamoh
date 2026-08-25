<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    public const ESTADOS = [
        'activo' => 'Activo',
        'vendido' => 'Vendido',
        'adjudicado' => 'Adjudicado',
        'baja' => 'Baja',
    ];

    public const PROPIETARIO_TIPOS = [
        'cliente' => 'Cliente',
        'empresa' => 'Empresa (flota propia)',
        'tercero' => 'Tercero',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'propietario_tipo' => 'cliente',
        'estado' => 'activo',
    ];

    protected $fillable = [
        'client_id', 'propietario_tipo', 'propietario_nombre', 'propietario_documento',
        'placa', 'marca', 'modelo', 'nro_motor', 'nro_serie', 'categoria', 'anio',
        'carroceria', 'color', 'combustible', 'partida_registral', 'valor',
        'soat_vence', 'revision_tecnica_vence', 'habilitacion_atu_vence',
        'estado', 'observaciones',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'soat_vence' => 'date',
        'revision_tecnica_vence' => 'date',
        'habilitacion_atu_vence' => 'date',
    ];

    /** Etiquetas de los vencimientos documentarios (campana y pantallas) */
    public const VENCIMIENTOS = [
        'soat_vence' => 'SOAT',
        'revision_tecnica_vence' => 'Revisión técnica',
        'habilitacion_atu_vence' => 'Habilitación ATU',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function garantias(): BelongsToMany
    {
        return $this->belongsToMany(Garantia::class, 'garantia_vehiculo')
            ->withPivot(['es_bien_futuro', 'acta_notarial', 'kardex', 'notario', 'fecha_acta', 'orden'])
            ->withTimestamps();
    }

    public function papeletas()
    {
        return $this->hasMany(Papeleta::class);
    }

    /** "PLACA — MARCA MODELO" para listas y buscadores */
    public function descripcion(): string
    {
        return trim("{$this->placa} — {$this->marca} {$this->modelo}");
    }
}
