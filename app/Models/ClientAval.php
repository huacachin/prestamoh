<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAval extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Aval';

    protected $table = 'client_avales';

    protected $fillable = [
        'client_id', 'nombre', 'dni', 'direccion', 'telefono',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->nombre;
    }
}
