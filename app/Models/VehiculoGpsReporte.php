<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\SinHorarioDeEliminacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de GPS de un vehículo en garantía: dónde estaba (coordenadas),
 * cuándo (fecha de registro) y una descripción. Simplificado el 09/10/2026
 * (Antony): antes llevaba puntos del recorrido, horarios, domicilio, fotos y
 * el texto para WhatsApp.
 */
class VehiculoGpsReporte extends Model implements SinHorarioDeEliminacion
{
    use Auditable;

    public const AUDIT_MODULO = 'Reporte GPS';

    protected $fillable = [
        'client_id', 'vehiculo_id', 'placa', 'fecha', 'latitud', 'longitud', 'descripcion', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /** "-12.014431, -76.824936", tal como se pegan y se copian. */
    public function coordenadas(): string
    {
        return $this->tieneCoordenadas() ? self::numero($this->latitud).', '.self::numero($this->longitud) : '';
    }

    /** Enlace para abrir el punto en Google Maps. */
    public function enlaceMaps(): string
    {
        return $this->tieneCoordenadas() ? 'https://maps.google.com/?q='.self::numero($this->latitud).','.self::numero($this->longitud) : '';
    }

    /** El decimal sin ceros de relleno: -12.0144310 → -12.014431. */
    private static function numero(mixed $valor): string
    {
        $texto = rtrim(rtrim(sprintf('%.7F', (float) $valor), '0'), '.');

        return $texto === '-0' ? '0' : $texto;
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return trim($this->placa.' '.$this->fecha?->format('d/m/Y H:i'));
    }
}
