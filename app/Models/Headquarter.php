<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    protected $fillable = [
        'name', 'empresa', 'ruc', 'slogan', 'direccion',
        'telefono', 'email', 'responsable', 'sort_order', 'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function activeUsers()
    {
        return $this->hasMany(User::class)->where('status', 'active');
    }
}
