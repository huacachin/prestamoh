<?php

namespace App\Models;

use App\Support\Garantias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garantia extends Model
{
    protected $table = 'garantias';

    /** Días de anticipación con que una garantía vigente aparece "por renovar" */
    public const DIAS_AVISO_RENOVACION = 7;

    public const ESTADOS = [
        'en_constitucion' => 'En constitución',
        'vigente' => 'Vigente',
        'cancelada' => 'Cancelada',
        'en_ejecucion' => 'En ejecución',
        'ejecutada' => 'Ejecutada',
    ];

    public const TIPOS = [
        'mobiliaria_vehicular' => 'Mobiliaria vehicular',
        'hipotecaria' => 'Hipotecaria',
    ];

    public const TIPO_PERSONAS = [
        'natural' => 'Persona natural',
        'juridica' => 'Persona jurídica',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'tipo' => 'mobiliaria_vehicular',
        'tipo_persona' => 'natural',
        'estado' => 'en_constitucion',
    ];

    protected $fillable = [
        'credit_id', 'client_id', 'codeudor_client_id', 'tipo', 'tipo_persona',
        'gps', 'custodia', 'monto_gravamen', 'estado', 'vigencia_hasta',
        'fecha_constitucion', 'requiere_revision', 'observaciones', 'registrado_por',
    ];

    protected $casts = [
        'gps' => 'boolean',
        'custodia' => 'boolean',
        'requiere_revision' => 'boolean',
        'monto_gravamen' => 'decimal:2',
        'vigencia_hasta' => 'date',
        'fecha_constitucion' => 'date',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function codeudor(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'codeudor_client_id');
    }

    public function vehiculos(): BelongsToMany
    {
        return $this->belongsToMany(Vehiculo::class, 'garantia_vehiculo')
            ->withPivot(['es_bien_futuro', 'acta_notarial', 'kardex', 'notario', 'fecha_acta', 'orden'])
            ->orderByPivot('orden')
            ->withTimestamps();
    }

    public function avisos(): HasMany
    {
        return $this->hasMany(SigmAviso::class)->orderBy('fecha_presentacion');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class)->orderByDesc('version');
    }

    public function tramitesNotariales(): HasMany
    {
        return $this->hasMany(TramiteNotarial::class)->latest('id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Vigentes cuya vigencia vence dentro de $dias (o ya venció) — badge y campana */
    public function scopePorRenovar(Builder $q, int $dias = self::DIAS_AVISO_RENOVACION): Builder
    {
        return $q->where('estado', 'vigente')
            ->whereNotNull('vigencia_hasta')
            ->where('vigencia_hasta', '<=', now()->addDays($dias)->toDateString());
    }

    /**
     * Recalcula estado y vigencia a partir del historial de avisos SIGM.
     * Se invoca al registrar/anular un aviso (self-heal, sin observers).
     */
    public function sincronizarConAvisos(): void
    {
        $avisos = $this->avisos()->where('estado', '!=', 'anulado')->get();

        $ultimaVigencia = $avisos->whereIn('tipo', ['constitucion', 'renovacion'])
            ->whereNotNull('vigencia_hasta')->max('vigencia_hasta');
        $constitucion = $avisos->firstWhere('tipo', 'constitucion');

        $estado = $this->estado;
        if ($avisos->contains('tipo', 'cancelacion')) {
            $estado = 'cancelada';
        } elseif ($avisos->contains('tipo', 'ejecucion')) {
            $estado = 'en_ejecucion';
        } elseif ($constitucion) {
            $estado = 'vigente';
        }

        $this->update([
            'estado' => $estado,
            'vigencia_hasta' => $ultimaVigencia,
            'fecha_constitucion' => $this->fecha_constitucion ?? $constitucion?->fecha_presentacion,
        ]);
    }

    /**
     * Tipo de garantía de un cliente: primero la tabla nueva; si el cliente
     * aún no tiene garantías registradas, cae al hack histórico sobre
     * clients.zona (App\Support\Garantias). Los consumidores actuales de
     * Garantias::de() migran aquí cuando el backfill esté validado.
     */
    public static function tipoDeCliente(Client $client): string
    {
        $tipo = self::where('client_id', $client->id)
            ->whereIn('estado', ['en_constitucion', 'vigente', 'en_ejecucion'])
            ->latest('id')->value('tipo');

        return match ($tipo) {
            'mobiliaria_vehicular' => Garantias::VEHICULAR,
            'hipotecaria' => Garantias::HIPOTECARIA,
            default => Garantias::de($client->zona),
        };
    }
}
