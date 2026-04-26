<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MassDeletionDetail extends Model
{
    protected $fillable = [
        'mass_deletion_id', 'installment_id', 'payment_id',
        'amount', 'fecha', 'tipo',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function massDeletion(): BelongsTo
    {
        return $this->belongsTo(MassDeletion::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CreditInstallment::class, 'installment_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
