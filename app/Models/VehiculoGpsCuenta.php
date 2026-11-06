<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuenta de la plataforma del GPS de un vehículo: aplicativo, enlace, usuario y contraseña
 * (06/11/2026, Trello). Una por placa. La contraseña se guarda cifrada (APP_KEY) y en la
 * auditoría sale como ••••••.
 */
class VehiculoGpsCuenta extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Cuenta GPS';

    public const AUDIT_ENMASCARAR = ['contrasena'];

    protected $fillable = [
        'vehiculo_id', 'aplicativo', 'enlace', 'usuario', 'contrasena', 'registrado_por', 'actualizado_por',
    ];

    protected $hidden = ['contrasena'];

    protected function casts(): array
    {
        return [
            'contrasena' => 'encrypted',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->vehiculo?->placa;
    }

    /** "gpsperu.com/login" → "https://gpsperu.com/login"; vacío → null. */
    public static function normalizarEnlace(?string $enlace): ?string
    {
        $enlace = trim((string) $enlace);
        if ($enlace === '') {
            return null;
        }

        return preg_match('#^https?://#i', $enlace) ? $enlace : 'https://'.$enlace;
    }
}
