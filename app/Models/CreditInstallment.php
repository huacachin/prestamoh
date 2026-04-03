<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditInstallment extends Model
{
    protected $fillable = [
        'credit_id', 'num_cuota', 'fecha_vencimiento',
        'importe_cuota', 'importe_interes', 'importe_aplicado',
        'interes_aplicado', 'importe_mora', 'pagado',
        'fecha_pago', 'observacion', 'usuario',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
        'importe_cuota'     => 'decimal:2',
        'importe_interes'   => 'decimal:2',
        'importe_aplicado'  => 'decimal:2',
        'interes_aplicado'  => 'decimal:2',
        'importe_mora'      => 'decimal:2',
        'pagado'            => 'boolean',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function saldoPendiente(): float
    {
        return ($this->importe_cuota + $this->importe_interes)
             - ($this->importe_aplicado + $this->interes_aplicado);
    }

    public function scopePendientes($q)
    {
        return $q->where('pagado', false);
    }
}
