<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representante legal de la empresa deudora. Historial 1-N con flag
 * `vigente`: cuando el poder cambia de titular, el anterior queda auditado
 * en vez de pisarse.
 */
class EmpresaRepresentante extends Model
{
    protected $table = 'empresa_representantes';

    protected $fillable = [
        'client_empresa_id', 'cargo', 'nombre', 'tipo_documento', 'documento',
        'sexo', 'nacionalidad', 'ocupacion', 'estado_civil', 'domicilio', 'vigente',
    ];

    protected $casts = ['vigente' => 'boolean'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(ClientEmpresa::class, 'client_empresa_id');
    }
}
