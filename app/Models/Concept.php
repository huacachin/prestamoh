<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Concept extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Concepto';

    protected $fillable = ['code', 'name', 'type', 'factor_ingreso', 'factor_egreso', 'status'];

    protected $casts = [
        'factor_ingreso' => 'decimal:2',
        'factor_egreso' => 'decimal:2',
    ];

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->name;
    }
}
