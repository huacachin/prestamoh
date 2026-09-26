<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOpening extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Apertura de caja';

    protected $fillable = [
        'fecha', 'hora', 'saldo_inicial', 'saldo_final', 'estado', 'moneda',
        'user_id', 'headquarter_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'saldo_inicial' => 'decimal:2',
        'saldo_final' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->fecha?->format('d/m/Y');
    }
}
