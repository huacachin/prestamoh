<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFee extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Mora';

    protected $fillable = ['credit_id', 'dias_mora', 'monto_mora'];

    protected $casts = [
        'monto_mora' => 'decimal:2',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }
}
