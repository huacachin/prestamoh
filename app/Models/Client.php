<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Cliente';

    /** % del capital declarado que se puede prestar (línea de crédito informativa) */
    public const LINEA_CREDITO_PCT = 25;

    protected $fillable = [
        'expediente', 'nombre', 'apellido_pat', 'apellido_mat',
        'tipo_documento', 'documento', 'fecha_registro', 'usuario', 'fecha_nacimiento', 'sexo',
        'nacionalidad', 'email', 'giro', 'ocupacion', 'estado_civil',
        'capital', 'celular1', 'celular2',
        'direccion', 'referencia', 'distrito', 'provincia', 'departamento',
        'zona', 'contacto_emergencia', 'telefono_contacto',
        'banco_haberes', 'cuenta_haberes', 'banco_cts', 'cuenta_cts',
        'afp', 'cussp', 'latitud', 'longitud', 'latitud2', 'longitud2', 'imagen',
        'observaciones', 'asesor_id', 'headquarter_id', 'status', 'es_relacionado',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'fecha_nacimiento' => 'date',
        'es_relacionado' => 'boolean',
    ];

    /**
     * Solo clientes DE VERDAD (excluye a las personas relacionadas —
     * copropietarios/codeudores creados desde el alta rápida, que no tienen
     * crédito, asesor ni expediente y no deben inflar listas ni reportes).
     */
    public function scopeTitulares($q)
    {
        return $q->where('es_relacionado', false);
    }

    public function fullName(): string
    {
        return trim("{$this->apellido_pat} {$this->apellido_mat} {$this->nombre}");
    }

    /** Línea de crédito = 25% del capital declarado (null si no tiene capital) */
    public function getCreditoAttribute(): ?float
    {
        return $this->capital !== null
            ? round((float) $this->capital * (self::LINEA_CREDITO_PCT / 100), 2)
            : null;
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClientAttachment::class);
    }

    public function avales(): HasMany
    {
        return $this->hasMany(ClientAval::class);
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /** Datos de persona jurídica (solo clientes con tipo_documento RUC). */
    public function empresa(): HasOne
    {
        return $this->hasOne(ClientEmpresa::class);
    }

    /**
     * ¿Es persona jurídica? Por tipo de documento (RUC) O por la forma del
     * número: 11 dígitos que empiezan en 10 ó 20 es un RUC aunque la ficha
     * diga DNI. La migración del legacy puso 'DNI' a TODOS los clientes
     * (MigrateLegacyData), así que las empresas migradas quedaron como
     * personas naturales y el wizard de contratos no activaba el modelo a.4
     * ni pedía al gerente general (02/10/2026, caso Gestion Energetica JM).
     * `clientes:normalizar-ruc` corrige las fichas; esto es la red de seguridad.
     */
    public function esPersonaJuridica(): bool
    {
        if (mb_strtoupper(trim((string) $this->tipo_documento)) === 'RUC') {
            return true;
        }

        return (bool) preg_match('/^(10|20)\d{9}$/', trim((string) $this->documento));
    }

    /** Vehículos donde este cliente es COPROPIETARIO (no titular). */
    public function vehiculosCompartidos(): BelongsToMany
    {
        return $this->belongsToMany(Vehiculo::class, 'cliente_vehiculo')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function expedientesJudiciales(): HasMany
    {
        return $this->hasMany(ExpedienteJudicial::class);
    }

    /** Direcciones GPS adicionales (10/10/2026): Casa y Negocio viven en latitud/longitud y latitud2/longitud2; el resto aquí. */
    public function ubicaciones(): HasMany
    {
        return $this->hasMany(ClientUbicacion::class, 'client_id')->orderBy('id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /**
     * Búsqueda por nombre como la teclea la gente (10/10/2026, Antony): cada
     * palabra del texto tiene que estar en el nombre completo, en cualquier
     * orden. "Torres Tapia Vda de Puma Julia Isabel", "Julia Torres" o "Torres"
     * encuentran a la misma persona; buscar el texto entero contra nombre,
     * apellido paterno y materno por separado no servía para nombres completos.
     */
    public function scopeNombreContiene($q, string $texto)
    {
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($palabras as $palabra) {
            $q->whereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ['%'.addcslashes($palabra, '%_\\').'%']);
        }

        return $q;
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return trim(($this->apellido_pat ?? '').' '.($this->apellido_mat ?? '').' '.($this->nombre ?? ''));
    }
}
