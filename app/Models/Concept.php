<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concept extends Model
{
    protected $fillable = ['code', 'name', 'type', 'factor_ingreso', 'factor_egreso', 'status'];

    protected $casts = [
        'factor_ingreso' => 'decimal:2',
        'factor_egreso'  => 'decimal:2',
    ];
}
