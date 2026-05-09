<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAval extends Model
{
    protected $table = 'client_avales';

    protected $fillable = [
        'client_id', 'nombre', 'dni', 'direccion', 'telefono',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
