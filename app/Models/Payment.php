<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'credit_id', 'installment_id', 'modo', 'tipo',
        'documento', 'nro_recibo', 'fecha', 'hora', 'monto',
        'moneda', 'tipo_cambio', 'detalle', 'asesor',
        'user_id', 'usuario', 'headquarter_id', 'latitud', 'longitud',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CreditInstallment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
