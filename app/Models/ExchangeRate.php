<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['fecha', 'compra', 'venta'];

    protected $casts = [
        'fecha' => 'date',
        'compra' => 'decimal:4',
        'venta' => 'decimal:4',
    ];
}
