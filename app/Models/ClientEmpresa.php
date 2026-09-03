<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos de persona jurídica del cliente (razón social y RUC viven en
 * clients.nombre / clients.documento; aquí va lo registral que exige el
 * contrato SIGM a.4). Ver la migración para el porqué.
 */
class ClientEmpresa extends Model
{
    protected $table = 'client_empresas';

    protected $fillable = [
        'client_id', 'partida_registral', 'oficina_registral', 'domicilio', 'correo',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function representantes(): HasMany
    {
        return $this->hasMany(EmpresaRepresentante::class);
    }

    /** El representante que firma HOY (el contrato cita siempre al vigente). */
    public function representanteVigente(): HasOne
    {
        return $this->hasOne(EmpresaRepresentante::class)->where('vigente', true)->latest('id');
    }
}
