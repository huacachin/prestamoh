<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    protected $fillable = [
        'name', 'empresa', 'ruc', 'slogan', 'direccion',
        'telefono', 'email', 'responsable', 'sort_order', 'status',
    ];
}
