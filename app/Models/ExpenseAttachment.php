<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Adjunto de egreso';

    protected $fillable = [
        'expense_id', 'filename', 'original_name',
        'path', 'thumb_path', 'mime', 'size', 'uploaded_by',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function thumbUrl(): string
    {
        return $this->thumb_path
            ? '/storage/'.ltrim($this->thumb_path, '/')
            : $this->url();
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->original_name ?: $this->filename;
    }
}
