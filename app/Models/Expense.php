<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['date', 'reason', 'detail', 'total', 'document_type', 'in_charge', 'image_path', 'user_id', 'headquarter_id'];

    protected $casts = ['date' => 'date', 'total' => 'decimal:2'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function headquarter(): BelongsTo { return $this->belongsTo(Headquarter::class); }
}
