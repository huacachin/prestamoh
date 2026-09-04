<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    /** % del capital declarado que se puede prestar (línea de crédito informativa) */
    public const LINEA_CREDITO_PCT = 25;

    protected $fillable = [
        'expediente', 'nombre', 'apellido_pat', 'apellido_mat',
        'tipo_documento', 'documento', 'fecha_registro', 'usuario', 'fecha_nacimiento', 'sexo',
        'nacionalidad', 'email', 'giro', 'ocupacion', 'estado_civil',
        'capital', 'celular1', 'celular2',
        'direccion', 'referencia', 'distrito', 'provincia', 'departamento',
        'zona', 'contacto_emergencia', 'telefono_contacto',
        'banco_haberes', 'cuenta_haberes', 'banco_cts', 'cuenta_cts',
        'afp', 'cussp', 'latitud', 'longitud', 'latitud2', 'longitud2', 'imagen',
        'observaciones', 'asesor_id', 'headquarter_id', 'status', 'es_relacionado',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'fecha_nacimiento' => 'date',
        'es_relacionado' => 'boolean',
    ];

    /**
     * Solo clientes DE VERDAD (excluye a las personas relacionadas —
     * copropietarios/codeudores creados desde el alta rápida, que no tienen
     * crédito, asesor ni expediente y no deben inflar listas ni reportes).
     */
    public function scopeTitulares($q)
    {
        return $q->where('es_relacionado', false);
    }

    public function fullName(): string
    {
        return trim("{$this->apellido_pat} {$this->apellido_mat} {$this->nombre}");
    }

    /** Línea de crédito = 25% del capital declarado (null si no tiene capital) */
    public function getCreditoAttribute(): ?float
    {
        return $this->capital !== null
            ? round((float) $this->capital * (self::LINEA_CREDITO_PCT / 100), 2)
            : null;
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /** Correos del cliente; `clients.email` espeja siempre al principal. */
    public function emails(): HasMany
    {
        return $this->hasMany(ClientEmail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClientAttachment::class);
    }

    public function avales(): HasMany
    {
        return $this->hasMany(ClientAval::class);
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /** Datos de persona jurídica (solo clientes con tipo_documento RUC). */
    public function empresa(): HasOne
    {
        return $this->hasOne(ClientEmpresa::class);
    }

    /** Vehículos donde este cliente es COPROPIETARIO (no titular). */
    public function vehiculosCompartidos(): BelongsToMany
    {
        return $this->belongsToMany(Vehiculo::class, 'cliente_vehiculo')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function expedientesJudiciales(): HasMany
    {
        return $this->hasMany(ExpedienteJudicial::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
