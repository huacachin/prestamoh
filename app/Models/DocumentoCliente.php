<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoCliente extends Model
{
    protected $table = 'documentos_cliente';

    public const TIPOS = [
        'anexo1' => 'Anexo 1 — Cronograma',
        'contrato' => 'Contrato',
        'anexo2' => 'Anexo 2 — Constancia de entrega',
    ];

    public const ESTADOS = [
        'emitido' => 'Emitido',
        'anulado' => 'Anulado',
    ];

    /** Defaults espejo de la migración: el modelo recién creado los ve sin refresh() */
    protected $attributes = [
        'version' => 1,
        'estado' => 'emitido',
    ];

    protected $fillable = [
        'client_id', 'credit_id', 'tipo', 'modelo', 'version', 'snapshot',
        'pdf_path', 'sha256', 'estado', 'generado_por',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Nombre de descarga: "anexo1-credito-28729-v2" */
    public function nombreArchivo(): string
    {
        return "{$this->tipo}-credito-{$this->credit_id}-v{$this->version}";
    }
}
