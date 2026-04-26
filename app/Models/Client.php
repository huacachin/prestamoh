<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'expediente', 'nombre', 'apellido_pat', 'apellido_mat',
        'tipo_documento', 'documento', 'fecha_registro', 'usuario', 'fecha_nacimiento', 'sexo',
        'email', 'giro', 'celular1', 'celular2',
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
        return trim("{$this->nombre} {$this->apellido_pat} {$this->apellido_mat}");
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

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
