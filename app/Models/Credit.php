<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credit extends Model
{
    protected $fillable = [
        'client_id', 'fecha_prestamo', 'fecha_actualizacion', 'importe', 'cuotas',
        'tipo_planilla', 'interes', 'interes_total', 'mora', 'mora1', 'mora2',
        'moneda', 'documento', 'glosa', 'situacion', 'estado',
        'refinanciado', 'cod_rem', 'gat', 'idcan', 'fecha_vencimiento', 'fecha_cancelacion',
        'asesor', 'user_id', 'usuario', 'headquarter_id',
    ];

    protected $casts = [
        'fecha_prestamo'      => 'date',
        'fecha_actualizacion' => 'date',
        'fecha_vencimiento'   => 'date',
        'fecha_cancelacion'   => 'date',
        'importe'           => 'decimal:2',
        'interes'           => 'decimal:4',
        'interes_total'     => 'decimal:2',
        'mora'              => 'decimal:2',
        'refinanciado'      => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CreditInstallment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(LateFee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function tipoPlanillaLabel(): string
    {
        return match ((int) $this->tipo_planilla) {
            1 => 'Semanal',
            3 => 'Mensual',
            4 => 'Diario',
            default => 'Otro',
        };
    }

    public function scopeActivo($q)
    {
        return $q->where('situacion', 'Activo');
    }
}
