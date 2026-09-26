<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Tipo de cambio';

    protected $fillable = ['fecha', 'compra', 'venta'];

    protected $casts = [
        'fecha' => 'date',
        'compra' => 'decimal:4',
        'venta' => 'decimal:4',
    ];

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->fecha?->format('d/m/Y');
    }
}
