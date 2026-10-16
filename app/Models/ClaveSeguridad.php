<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * Clave de seguridad de segundo nivel (16/10/2026, Antony). La primera,
 * ROL_DIRECTOR, la custodia el gerente y se pide cada vez que un cambio de rol
 * entra o sale de Director (alta y edición de usuarios). Solo vive el hash;
 * se crea o cambia con el comando usuarios:clave-director.
 */
class ClaveSeguridad extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Clave de seguridad';

    public const AUDIT_ENMASCARAR = ['hash'];

    /** Clave que protege dar o quitar el rol Director. */
    public const ROL_DIRECTOR = 'rol-director';

    /** Largo mínimo al establecerla (el gerente puede elegir la suya). */
    public const LARGO_MINIMO = 10;

    protected $table = 'claves_seguridad';

    protected $fillable = ['nombre', 'hash', 'actualizado_por'];

    protected $hidden = ['hash'];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function auditNombre(): ?string
    {
        return $this->nombre;
    }

    public static function definida(string $nombre): bool
    {
        return static::where('nombre', $nombre)->exists();
    }

    /** true solo si la clave existe y el intento coincide. */
    public static function verificar(string $nombre, string $intento): bool
    {
        $hash = static::where('nombre', $nombre)->value('hash');

        return $intento !== '' && is_string($hash) && Hash::check($intento, $hash);
    }

    /** Crea o reemplaza la clave guardando solo su hash. */
    public static function establecer(string $nombre, string $clave, ?int $usuarioId = null): self
    {
        return static::updateOrCreate(['nombre' => $nombre], ['hash' => Hash::make($clave), 'actualizado_por' => $usuarioId]);
    }

    /**
     * Clave aleatoria legible para dictar o anotar: cuatro grupos de cuatro
     * (sin 0/O, 1/l/I), p. ej. "Kq7m-Xw3t-Pz8v-Rh4n".
     */
    public static function generar(int $grupos = 4): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $partes = [];
        for ($g = 0; $g < $grupos; $g++) {
            $parte = '';
            for ($i = 0; $i < 4; $i++) {
                $parte .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $partes[] = $parte;
        }

        return implode('-', $partes);
    }
}
