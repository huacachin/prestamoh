<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección GPS adicional del cliente (10/10/2026). Casa y Negocio NO están
 * aquí: viven en clients.latitud/longitud y latitud2/longitud2. Los cambios
 * se registran a mano en la auditoría con el cliente como sujeto
 * (App\Livewire\Clients\Gps), para que la tablita de cambios de la pestaña
 * GPS los liste junto a los de Casa y Negocio.
 */
class ClientUbicacion extends Model
{
    protected $table = 'client_ubicaciones';

    protected $fillable = ['client_id', 'nombre', 'latitud', 'longitud', 'user_id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Quién la registró (para la tabla de la pestaña GPS cuando no hay rastro en la auditoría). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): ?string
    {
        return ($this->latitud && $this->longitud) ? "https://maps.google.com/?q={$this->latitud},{$this->longitud}" : null;
    }
}
