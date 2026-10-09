<?php

namespace App\Models;

use App\Support\Audit;
use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    /** Contratos en los que esta persona figura como codeudor (enlace exacto, desde el 10/10/2026). */
    public function contratosComoCodeudor(): HasMany
    {
        return $this->hasMany(DocumentoCliente::class, 'codeudor_client_id');
    }

    /**
     * Préstamos en los que esta persona es copropietaria (10/10/2026, Antony):
     * por cada titular, su ÚLTIMO crédito con contrato emitido. El enlace es
     * exacto cuando el contrato guardó al codeudor (contratos desde el 10/10);
     * si no, se deduce por el vehículo compartido → su dueño → su último
     * contrato. Es lo que rellena Exp./T.Credito/Giro/Asesor de la persona
     * relacionada en el listado y el bloque "Copropietario en" de su ficha.
     *
     * @return Collection<int, array{titular: Client, credit: ?Credit, documento: ?DocumentoCliente, placas: list<string>, exacto: bool}>
     */
    public function copropiedades(): Collection
    {
        $porTitular = collect();

        $exactos = $this->contratosComoCodeudor()
            ->where('tipo', 'contrato')->where('estado', '!=', 'anulado')
            ->with('credit.client.asesor')->orderByDesc('id')->get();
        foreach ($exactos as $doc) {
            $titular = $doc->credit?->client;
            if (! $titular || $porTitular->has($titular->id)) {
                continue;
            }
            $porTitular->put($titular->id, ['titular' => $titular, 'credit' => $doc->credit, 'documento' => $doc, 'placas' => [], 'exacto' => true]);
        }

        foreach ($this->vehiculosCompartidos()->with('client.asesor')->get() as $v) {
            $titular = $v->client;
            if (! $titular) {
                continue;
            }
            if ($porTitular->has($titular->id)) {
                $fila = $porTitular->get($titular->id);
                $fila['placas'][] = $v->placa;
                $porTitular->put($titular->id, $fila);

                continue;
            }
            $doc = DocumentoCliente::where('tipo', 'contrato')->where('estado', '!=', 'anulado')
                ->whereHas('credit', fn ($q) => $q->where('client_id', $titular->id))
                ->with('credit')->orderByDesc('id')->first();
            $porTitular->put($titular->id, ['titular' => $titular, 'credit' => $doc?->credit, 'documento' => $doc, 'placas' => [$v->placa], 'exacto' => false]);
        }

        return $porTitular->values();
    }

    /**
     * Lo que el listado muestra en Exp./T.Credito/Giro/Asesor de una persona
     * relacionada: los datos del titular (y el crédito) de su primera copropiedad.
     *
     * @return array{titular: Client, credit: ?Credit, placas: list<string>, exacto: bool}|null
     */
    public function herenciaDeCopropietario(): ?array
    {
        $c = $this->copropiedades()->first();

        return $c ? ['titular' => $c['titular'], 'credit' => $c['credit'], 'placas' => $c['placas'], 'exacto' => $c['exacto']] : null;
    }

    /**
     * 10/10/2026 (Antony): "si el cliente copropietario llegara a tener un
     * préstamo por sí solo". La persona relacionada pasa a TITULAR en el acto:
     * recibe el siguiente expediente del correlativo de clientes, deja de ser
     * relacionada (en el listado sale como un cliente más, con sus propios
     * datos) y conserva sus vínculos de copropietaria. Lo llama Credits\Create
     * al registrar su primer crédito; el alta normal de cliente ya hacía lo
     * mismo con el documento repetido. Devuelve el expediente asignado.
     */
    public function promoverATitular(): ?string
    {
        if (! $this->es_relacionado) {
            return null;
        }

        return DB::transaction(function () {
            $correl = (int) (DB::table('correlativos')->where('tipo', 'Cliente')->lockForUpdate()->value('correl') ?? 0);
            $expediente = filled($this->expediente) ? (string) $this->expediente : (string) ($correl + 1);
            $usuario = auth()->user();

            $this->sinAuditoriaAutomatica(fn () => $this->update([
                'es_relacionado' => false,
                'expediente' => $expediente,
                'fecha_registro' => $this->fecha_registro ?? now()->toDateString(),
                'usuario' => $this->usuario ?? ($usuario->username ?? $usuario->name ?? null),
                'asesor_id' => $this->asesor_id ?? $usuario?->id,
            ]));
            if ((int) $expediente > $correl) {
                DB::table('correlativos')->updateOrInsert(['tipo' => 'Cliente'], ['correl' => (int) $expediente, 'updated_at' => now()]);
            }
            Audit::log("Pasó a titular con el expediente {$expediente} (era persona relacionada) al registrar su primer crédito", $this, ['expediente' => $expediente]);

            return $expediente;
        });
    }

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
