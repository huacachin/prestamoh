<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MassDeletion extends Model
{
    protected $fillable = [
        'credit_id',
        'amount',
        'date',
        'time',
        'user',
        'advisor',
        'performed_by',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
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
