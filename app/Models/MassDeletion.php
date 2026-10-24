<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MassDeletion extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Cobro por lotes';

    protected $fillable = [
        'credit_id',
        'amount',
        'date',
        'time',
        'user',
        'advisor',
        'performed_by',
        'hasta_cuota',
        'interes_exonerado',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        // "Calcular cancelación" (23/10): la cuota elegida y, si canceló, el interés exonerado.
        'hasta_cuota' => 'integer',
        'interes_exonerado' => 'decimal:2',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(MassDeletionDetail::class);
    }
}
