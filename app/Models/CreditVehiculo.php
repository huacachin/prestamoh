<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vehículo que garantiza un préstamo sin haberse registrado para él: el mismo de un préstamo anterior,
 * vuelto a prendar (20/11/2026, Antony: "un cliente puede prendar el mismo vehículo si ya ha pagado
 * parte de su préstamo"). Guarda también que la cuenta del GPS de esa placa se confirmó para este
 * préstamo. Lo lee App\Support\RutaExpediente.
 */
class CreditVehiculo extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Vehículo del préstamo';

    protected $table = 'credit_vehiculo';

    protected $fillable = ['credit_id', 'vehiculo_id', 'prendado', 'gps_confirmado_at', 'gps_confirmado_por', 'registrado_por'];

    protected function casts(): array
    {
        return [
            'prendado' => 'boolean',
            'gps_confirmado_at' => 'datetime',
        ];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function auditNombre(): ?string
    {
        return 'préstamo #'.$this->credit_id.' · '.($this->vehiculo?->placa ?? 'vehículo #'.$this->vehiculo_id);
    }
}
