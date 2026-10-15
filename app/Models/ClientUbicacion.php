<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección GPS del cliente (pestaña GPS). Desde el 15/10/2026 aquí viven
 * TODAS: Casa y Negocio (tipo 'casa' / 'negocio', una por cliente, con nombre
 * editable) y las adicionales (tipo 'otra'). user_id y registrado_at son el
 * último usuario y la fecha en que se registraron las coordenadas. Los cambios
 * se registran a mano en la auditoría con el cliente como sujeto
 * (App\Livewire\Clients\Gps), que es lo que lee "Ver movimientos".
 */
class ClientUbicacion extends Model
{
    /** Nombres por defecto de las direcciones fijas. */
    public const TIPOS = ['casa' => 'Casa', 'negocio' => 'Negocio'];

    protected $table = 'client_ubicaciones';

    protected $fillable = ['client_id', 'tipo', 'nombre', 'latitud', 'longitud', 'user_id', 'registrado_at'];

    protected $casts = [
        'registrado_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Quién registró las coordenadas por última vez. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function esFija(): bool
    {
        return array_key_exists($this->tipo, self::TIPOS);
    }

    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    public function url(): ?string
    {
        return $this->tieneCoordenadas() ? "https://maps.google.com/?q={$this->latitud},{$this->longitud}" : null;
    }
}
