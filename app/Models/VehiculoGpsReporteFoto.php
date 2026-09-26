<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto adjunta a un reporte de GPS de vehículo (26/09/2026). */
class VehiculoGpsReporteFoto extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Foto de reporte GPS';

    protected $fillable = ['reporte_id', 'path', 'thumb_path', 'original_name', 'mime', 'size'];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(VehiculoGpsReporte::class, 'reporte_id');
    }

    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function thumbUrl(): string
    {
        return $this->thumb_path ? '/storage/'.ltrim($this->thumb_path, '/') : $this->url();
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return $this->original_name;
    }
}
