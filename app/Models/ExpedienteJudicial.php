<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpedienteJudicial extends Model
{
    protected $table = 'expedientes_judiciales';

    /** Formato estándar del PJ peruano: 04388-2024-0-3209-JP-CI-01 */
    public const REGEX_NRO = '/^\d{5}-\d{4}-\d{1,2}-\d{4}-[A-Z]{2}-[A-Z]{2}-\d{2}$/';

    public const CUADERNOS = [
        'principal' => 'Principal',
        'cautelar' => 'Cautelar',
    ];

    public const ESTADOS_PRINCIPAL = [
        'en_tramite' => 'En trámite',
        'en_ejecucion' => 'En ejecución',
        'condonado' => 'Condonado',
        'cancelado' => 'Cancelado',
        'desistido' => 'Desistido',
        'archivado' => 'Archivado',
    ];

    public const ESTADOS_CAUTELAR = [
        'solicitada' => 'Medida solicitada',
        'concedida' => 'Medida concedida',
        'oficio_entregado' => 'Oficio entregado',
        'captura_informado' => 'Captura informada',
        'capturado' => 'Capturado',
        'inscrito' => 'Inscrito',
        'levantado' => 'Levantado',
        'rechazada' => 'Rechazada',
    ];

    public const VIAS = [
        'captura_vehicular' => 'Captura vehicular',
        'inscripcion' => 'Embargo por inscripción',
        'planilla' => 'Descuento por planilla',
        'otra' => 'Otra',
    ];

    public const FORMAS_MEDIDA = [
        'secuestro' => 'Secuestro conservativo',
        'inscripcion' => 'Embargo en forma de inscripción',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'cuaderno' => 'principal',
        'estado' => 'en_tramite',
    ];

    protected $fillable = [
        'client_id', 'credit_id', 'garantia_id', 'exp_interno', 'nro_expediente',
        'cuaderno', 'expediente_padre_id', 'juzgado', 'distrito_judicial', 'materia',
        'proceso', 'juez', 'secretario', 'via', 'forma_medida', 'bien_descripcion',
        'monto_pretension', 'estado', 'asesor_responsable_id', 'fecha_inicio',
        'requiere_revision', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'monto_pretension' => 'decimal:2',
        'requiere_revision' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_responsable_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'expediente_padre_id');
    }

    public function cautelares(): HasMany
    {
        return $this->hasMany(self::class, 'expediente_padre_id');
    }

    public function actuaciones(): HasMany
    {
        return $this->hasMany(ActuacionJudicial::class, 'expediente_id')
            ->orderByDesc('fecha')->orderByDesc('id');
    }

    public function plazos(): HasMany
    {
        return $this->hasMany(PlazoJudicial::class, 'expediente_id')->orderBy('fecha_vencimiento');
    }

    /** Catálogo de estados según el cuaderno de ESTE expediente */
    public function estadosDisponibles(): array
    {
        return $this->cuaderno === 'cautelar' ? self::ESTADOS_CAUTELAR : self::ESTADOS_PRINCIPAL;
    }

    public function estadoLabel(): string
    {
        return $this->estadosDisponibles()[$this->estado]
            ?? (self::ESTADOS_PRINCIPAL + self::ESTADOS_CAUTELAR)[$this->estado]
            ?? $this->estado;
    }

    public static function formatoValido(string $nro): bool
    {
        return (bool) preg_match(self::REGEX_NRO, trim($nro));
    }

    /**
     * Deriva el número del cuaderno cautelar N desde el principal:
     * 04388-2024-0-3209-JP-CI-01 → 04388-2024-1-3209-JP-CI-01.
     * Elimina de raíz los errores de tipeo del Excel (doble guion, espacios,
     * cautelar registrado con '-0-').
     */
    public static function nroCautelarDesde(string $nroPrincipal, int $n = 1): string
    {
        $partes = explode('-', trim($nroPrincipal));
        if (count($partes) >= 3) {
            $partes[2] = (string) $n;
        }

        return implode('-', $partes);
    }
}
