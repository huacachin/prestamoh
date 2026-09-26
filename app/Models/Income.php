<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Income extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Ingreso';

    protected $fillable = ['date', 'reason', 'modo', 'documento', 'asesor', 'detail', 'total', 'image_path', 'user_id', 'headquarter_id', 'caja', 'parent_id'];

    protected $casts = ['date' => 'date', 'total' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncomeAttachment::class);
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->reason;
    }
}
