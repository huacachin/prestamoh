<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOpening extends Model
{
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
}
