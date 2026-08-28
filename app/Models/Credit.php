<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credit extends Model
{
    /** Tasa de mora fija por cuota: 5% de la cuota, ÷7 semanal / ÷30 mensual */
    public const TASA_MORA_PCT = 5;

    /**
     * Mora diaria según la regla del 5% por cuota (aplica a créditos vigentes
     * y nuevos). Los diarios (tipo 4) mantienen su mora1 histórico.
     */
    public function moraDiaria(float $cuota): float
    {
        return match ((int) $this->tipo_planilla) {
            1 => round($cuota * self::TASA_MORA_PCT / 100 / 7, 2),
            3 => round($cuota * self::TASA_MORA_PCT / 100 / 30, 2),
            default => (float) $this->mora1,
        };
    }

    protected $fillable = [
        'client_id', 'fecha_prestamo', 'fecha_actualizacion', 'importe', 'cuotas',
        'tipo_planilla', 'interes', 'interes_total', 'mora', 'mora1', 'mora2',
        'moneda', 'documento', 'glosa', 'situacion', 'estado',
        'refinanciado', 'cod_rem', 'gat', 'idcan', 'fecha_vencimiento', 'fecha_cancelacion',
        'asesor', 'user_id', 'usuario', 'headquarter_id',
    ];

    protected $casts = [
        'fecha_prestamo' => 'date',
        'fecha_actualizacion' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_cancelacion' => 'date',
        'importe' => 'decimal:2',
        'interes' => 'decimal:4',
        'interes_total' => 'decimal:2',
        'mora' => 'decimal:2',
        'refinanciado' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CreditInstallment::class);
    }

    /**
     * Saldo pendiente del cronograma (cap + int + exc − aplicados). Regla de
     * negocio (20/08): un crédito con saldo no se puede cancelar a mano ni
     * re-activar — cancelar condonaría deuda viva, y re-activar un
     * cancelado/refinanciado con saldo reabriría deuda condonada/trasladada.
     */
    public function saldoPendienteCronograma(): float
    {
        return round((float) $this->installments()
            ->selectRaw('SUM(importe_cuota + importe_interes + importe_excedente
                - importe_aplicado - interes_aplicado - excedente_aplicado) as s')
            ->value('s'), 2);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function massDeletions(): HasMany
    {
        return $this->hasMany(MassDeletion::class)->orderByDesc('date')->orderByDesc('time');
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(LateFee::class);
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
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
