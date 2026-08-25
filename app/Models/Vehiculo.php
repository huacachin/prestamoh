<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $fillable = [
        'client_id', 'placa', 'marca', 'modelo', 'nro_motor', 'nro_serie',
        'categoria', 'anio_modelo', 'carroceria', 'color', 'combustible', 'valor',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** "PLACA — MARCA MODELO" para listas */
    public function descripcion(): string
    {
        return trim("{$this->placa} — {$this->marca} {$this->modelo}");
    }
}
