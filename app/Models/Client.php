<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** % del capital declarado que se puede prestar (línea de crédito informativa) */
    public const LINEA_CREDITO_PCT = 25;

    protected $fillable = [
        'expediente', 'nombre', 'apellido_pat', 'apellido_mat',
        'tipo_documento', 'documento', 'fecha_registro', 'usuario', 'fecha_nacimiento', 'sexo',
        'email', 'giro', 'capital', 'celular1', 'celular2',
        'direccion', 'referencia', 'distrito', 'provincia', 'departamento',
        'zona', 'contacto_emergencia', 'telefono_contacto',
        'banco_haberes', 'cuenta_haberes', 'banco_cts', 'cuenta_cts',
        'afp', 'cussp', 'latitud', 'longitud', 'latitud2', 'longitud2', 'imagen',
        'observaciones', 'asesor_id', 'headquarter_id', 'status',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'fecha_nacimiento' => 'date',
    ];

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

    public function attachments(): HasMany
    {
        return $this->hasMany(ClientAttachment::class);
    }

    public function avales(): HasMany
    {
        return $this->hasMany(ClientAval::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
