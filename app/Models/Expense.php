<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Egreso';

    protected $fillable = ['date', 'reason', 'modo', 'documento', 'detail', 'total', 'document_type', 'in_charge', 'image_path', 'user_id', 'headquarter_id', 'caja', 'parent_id', 'mass_deletion_id'];

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
        return $this->hasMany(ExpenseAttachment::class);
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->reason;
    }
}
