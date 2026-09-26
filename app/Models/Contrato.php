<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contrato extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Contrato';

    public const AUDIT_EXCLUIR = ['datos_snapshot', 'sha256'];

    protected $table = 'contratos';

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'emitido' => 'Emitido',
        'anulado' => 'Anulado',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'tipo' => 'garantia_mobiliaria',
        'version' => 1,
        'estado' => 'emitido',
    ];

    protected $fillable = [
        'garantia_id', 'credit_id', 'client_id', 'numero', 'tipo', 'version',
        'parametros', 'datos_snapshot', 'estado', 'pdf_path', 'sha256', 'generado_por',
    ];

    protected $casts = [
        'parametros' => 'array',
        'datos_snapshot' => 'array',
    ];

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(LegalAdjunto::class, 'adjuntable');
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->numero;
    }
}
