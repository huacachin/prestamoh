<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Sucursal';

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

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->name;
    }
}
