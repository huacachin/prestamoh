<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\Credit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documentos\Nacionalidades;
use App\Support\Ubigeo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Edit extends Component
{
    public Client $client;

    public int $clientId;

    // ── Campos editables (homologados a clienteedit.php) ──
    public string $apellido_pat = '';

    public string $apellido_mat = '';

    public string $nombre = '';

    public string $tipo_documento = 'DNI'; // readonly display

    public string $documento = '';

    public ?string $fecha_nacimiento = null;

    public string $sexo = 'M';

    public string $status = 'active';

    public ?string $direccion = null;

    /**
     * Datos que el contrato exige y que hasta ahora solo se capturaban en el
     * alta: si el asesor se equivocaba, el guard de emisión bloqueaba el
     * contrato sin darle forma de corregirlo desde la UI.
     */
    public ?string $distrito = null;

    public string $provincia = 'Lima';

    public ?string $departamento = null;

    /** Misma cascada de ubigeo que en Create (ver el comentario de allá). */
    public function updatedDepartamento(): void
    {
        $this->departamento = Ubigeo::resolverDepartamento($this->departamento) ?? $this->departamento;
        if (Ubigeo::resolverProvincia($this->departamento, $this->provincia) === null) {
            $this->provincia = Ubigeo::provinciasDe($this->departamento)[0] ?? $this->provincia;
            $this->updatedProvincia();
        }
    }

    public function updatedProvincia(): void
    {
        // Provincia de OTRO departamento (la API o un set directo la mandan
        // antes que el departamento): arrastra a su departamento y normaliza.
        if (Ubigeo::resolverProvincia($this->departamento, $this->provincia) === null) {
            $ubicada = Ubigeo::buscarProvincia($this->provincia);
            if ($ubicada !== null) {
                [$this->departamento, $this->provincia] = $ubicada;
            }
        } else {
            $this->provincia = Ubigeo::resolverProvincia($this->departamento, $this->provincia);
        }

        // El distrito se conserva si pertenece a la nueva provincia; si la
        // provincia quedo fuera del catalogo (historico) no se toca nada.
        $distritos = Ubigeo::distritosDe($this->departamento, $this->provincia);
        if (filled($this->distrito) && $distritos !== []) {
            $enCatalogo = collect($distritos)
                ->contains(fn ($d) => mb_strtoupper($d) === mb_strtoupper(trim((string) $this->distrito)));
            if (! $enCatalogo) {
                $this->distrito = null;
            }
        }
    }

    public ?string $nacionalidad = null;

    /**
     * Correos del cliente (04/09): filas {id|null, email, principal}. El
     * PRINCIPAL es el que sale en contratos — clients.email lo espeja
     * (ClientEmail::espejar) al guardar. Máximo uno principal.
     *
     * @var array<int, array{id: int|null, email: string, principal: bool}>
     */
    public array $correos = [];

    public function agregarCorreo(): void
    {
        $this->correos[] = ['id' => null, 'email' => '', 'principal' => count($this->correos) === 0];
    }

    public function quitarCorreo(int $i): void
    {
        if (! isset($this->correos[$i])) {
            return;
        }

        // El último correo con contenido no se quita: la cláusula de
        // notificaciones del contrato lo exige. (Una fila vacía sí se puede.)
        $conContenido = collect($this->correos)->filter(fn ($c) => filled($c['email']))->count();
        if (filled($this->correos[$i]['email']) && $conContenido <= 1) {
            $this->dispatch('errorAlert', ['message' => 'Es el único correo del cliente: agrega otro antes de quitarlo (los contratos lo exigen).']);

            return;
        }

        $eraPrincipal = $this->correos[$i]['principal'];
        unset($this->correos[$i]);
        $this->correos = array_values($this->correos);
        if ($eraPrincipal && $this->correos !== []) {
            $this->correos[0]['principal'] = true; // auto-promoción
        }
    }

    public function marcarPrincipal(int $i): void
    {
        if (! isset($this->correos[$i])) {
            return;
        }
        foreach ($this->correos as $j => $c) {
            $this->correos[$j]['principal'] = ($j === $i);
        }
    }

    public string $ocupacion = 'transportista';

    public string $estado_civil = 'soltero';

    public ?string $referencia = null;

    public ?string $giro = null;            // Legacy: telefono1 → giro

    public $capital = null;                 // Capital declarado (línea de crédito = 25%)

    public ?string $telefono_secundario = null; // Legacy: telefono2

    public ?string $celular1 = null;

    public ?string $celular2 = null;

    public ?string $zona = null;            // Legacy: T.Crédito

    /** Pestaña activa del card: 'datos' | 'vehiculos' | 'adjuntos' (28/08). */
    #[Url(as: 'tab', except: 'datos')]
    public string $tab = 'datos';

    public ?int $asesor_id = null;

    // ── Casa / Negocio (reset coords) ──
    public bool $casa = false;

    public bool $negocio = false;

    public ?string $latitud = null;

    public ?string $latitud2 = null;

    // ── Permisos cacheados ──
    public bool $puedeEditarIdentidad = false;

    public bool $puedeGuardar = true;

    public bool $tieneCreditosVigentes = false;

    public $asesores;

    protected function rules(): array
    {
        $isRuc = $this->tipo_documento === 'RUC';

        return [
            'nombre' => 'required|string|max:200',
            'apellido_pat' => $isRuc ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido_mat' => 'nullable|string|max:100',
            'documento' => 'required|string|max:20|unique:clients,documento,'.$this->clientId,
            'fecha_nacimiento' => 'nullable|date',
            'sexo' => 'required|in:M,F',
            'status' => 'required|in:active,inactive',
            'direccion' => 'nullable|string|max:255',
            'distrito' => 'nullable|string|max:100',
            'provincia' => 'required|string|max:100',
            'departamento' => 'nullable|string|max:100',
            // Acepta las opciones vigentes y el valor histórico ya guardado
            'nacionalidad' => 'nullable|string|in:'.implode(',', Nacionalidades::paraValor($this->nacionalidad)),
            'correos' => 'array',
            'correos.*.email' => 'required|email|max:150',
            'ocupacion' => 'required|string|max:100',
            'estado_civil' => 'required|string|max:100',
            'referencia' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:100',
            'capital' => 'nullable|numeric|min:0',
            'telefono_secundario' => 'nullable|string|max:20',
            'celular1' => 'nullable|string|max:20',
            'celular2' => 'nullable|string|max:20',
            // Acepta las opciones vigentes y el valor histórico ya guardado
            'zona' => 'nullable|string|max:100',
            'asesor_id' => 'nullable|exists:users,id',
        ];
    }

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);

        // Analista (scope-propio): solo SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        $this->clientId = $id;

        $user = auth()->user();
        $this->puedeEditarIdentidad = $user?->can('clientes.editar-identidad') ?? false;
        // Quien tiene scope propio (analista, cobranzas) puede ver pero no guardar cambios.
        $this->puedeGuardar = ! ($user?->can('clientes.scope-propio') ?? false);

        $this->tieneCreditosVigentes = Credit::where('client_id', $id)
            ->where('situacion', 'Activo')
            ->exists();

        $this->asesores = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $c = $this->client;
        $this->apellido_pat = (string) ($c->apellido_pat ?? '');
        $this->apellido_mat = (string) ($c->apellido_mat ?? '');
        $this->nombre = (string) ($c->nombre ?? '');
        $this->tipo_documento = $c->tipo_documento ?? 'DNI';
        $this->documento = (string) ($c->documento ?? '');
        $this->fecha_nacimiento = $c->fecha_nacimiento?->format('Y-m-d');
        $this->sexo = $c->sexo ?? 'M';
        $this->status = $c->status ?? 'active';
        $this->direccion = $c->direccion;
        $this->distrito = $c->distrito;
        // Resolución case-insensitive al catálogo ("LIMA" migrado → "Lima");
        // un valor histórico fuera del catálogo se conserva tal cual. Sin
        // departamento guardado (la migración lo deja NULL) hereda el de la
        // provincia: lo que muestra el select es lo que se guardará.
        $depGuardado = Ubigeo::resolverDepartamento($c->departamento) ?? $c->departamento;
        $ubicada = Ubigeo::buscarProvincia($c->provincia);
        $this->departamento = $depGuardado ?: ($ubicada[0] ?? 'Lima');
        $this->provincia = Ubigeo::resolverProvincia($this->departamento, $c->provincia)
            ?? ($c->provincia ?: 'Lima');
        $this->nacionalidad = $c->nacionalidad ?: Nacionalidades::DEFECTO;
        $this->correos = $c->emails()->orderByDesc('principal')->orderBy('id')
            ->get(['id', 'email', 'principal'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'email' => (string) $e->email, 'principal' => (bool) $e->principal])
            ->all();
        // Ficha previa al backfill o sin correo: si la columna tiene algo
        // que la tabla no, se muestra para que no se pierda al guardar.
        if ($this->correos === [] && filled($c->email)) {
            $this->correos = [['id' => null, 'email' => (string) $c->email, 'principal' => true]];
        }
        $this->ocupacion = filled($c->ocupacion) ? (string) $c->ocupacion : 'transportista';
        $this->estado_civil = filled($c->estado_civil) ? (string) $c->estado_civil : 'soltero';
        $this->referencia = $c->referencia;
        $this->giro = $c->giro;
        $this->capital = $c->capital;
        // Legacy ntelefono2 → en nuestro modelo no había exacto; usamos telefono_contacto como almacén
        $this->telefono_secundario = $c->telefono_contacto;
        $this->celular1 = $c->celular1;
        $this->celular2 = $c->celular2;
        $this->zona = $c->zona;
        $this->asesor_id = $c->asesor_id;
        $this->latitud = $c->latitud;
        $this->latitud2 = $c->latitud2;
    }

    public function update()
    {
        if (! $this->puedeGuardar) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para guardar.']);

            return;
        }

        // El select manda el valor exacto, pero la ficha puede venir de una
        // migración o de un import con otra caja: normalizar antes de validar
        // evita rechazar 'peruano' por una diferencia de mayúsculas.
        if (filled($this->nacionalidad)) {
            $this->nacionalidad = Nacionalidades::normalizar($this->nacionalidad);
        }

        $this->validate();

        $data = [
            // Identidad: solo si SuperUsuario puede editar
            'apellido_pat' => $this->puedeEditarIdentidad ? $this->apellido_pat : $this->client->apellido_pat,
            'apellido_mat' => $this->puedeEditarIdentidad ? $this->apellido_mat : $this->client->apellido_mat,
            'nombre' => $this->puedeEditarIdentidad ? $this->nombre : $this->client->nombre,
            'documento' => $this->puedeEditarIdentidad ? $this->documento : $this->client->documento,

            // Resto editables por todos (excepto Asesor que ni siquiera ve el botón)
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'sexo' => $this->sexo,
            'direccion' => $this->direccion,
            'distrito' => $this->distrito,
            'provincia' => $this->provincia,
            'departamento' => $this->departamento,
            'nacionalidad' => filled($this->nacionalidad) ? $this->nacionalidad : null,
            'ocupacion' => $this->ocupacion,
            'estado_civil' => $this->estado_civil,
            'referencia' => $this->referencia,
            'giro' => $this->giro,
            'capital' => $this->capital !== null && $this->capital !== '' ? $this->capital : null,
            'telefono_contacto' => $this->telefono_secundario,
            'celular1' => $this->celular1,
            'celular2' => $this->celular2,
            'zona' => $this->zona,
            'asesor_id' => $this->asesor_id,
            'updated_at' => now(),
        ];

        // Estado: solo si NO hay créditos vigentes (igual que legacy)
        if (! $this->tieneCreditosVigentes) {
            $data['status'] = $this->status;
        }

        // Casa / Negocio: reset coords (solo SuperUsuario)
        if ($this->puedeEditarIdentidad) {
            if ($this->casa) {
                $data['latitud'] = null;
                $data['longitud'] = null;
            }
            if ($this->negocio) {
                $data['latitud2'] = null;
                $data['longitud2'] = null;
            }
        }

        // Duplicados en la lista (case-insensitive) antes de tocar nada.
        $normalizados = collect($this->correos)->map(fn ($c) => mb_strtolower(trim($c['email'])));
        if ($normalizados->duplicates()->isNotEmpty()) {
            $this->addError('correos', 'Hay correos repetidos en la lista.');

            return;
        }

        DB::transaction(function () use ($data) {
            $this->client->update($data);

            // Correos: borra los quitados, upserta los presentes, un principal.
            $ids = collect($this->correos)->pluck('id')->filter()->all();
            DB::table('client_emails')->where('client_id', $this->clientId)
                ->whereNotIn('id', $ids ?: [0])->delete();

            $hayPrincipal = collect($this->correos)->contains(fn ($c) => $c['principal']);
            foreach ($this->correos as $i => $c) {
                $principal = $hayPrincipal ? $c['principal'] : $i === 0;
                DB::table('client_emails')->updateOrInsert(
                    ['client_id' => $this->clientId, 'email' => trim($c['email'])],
                    ['principal' => $principal ? 1 : 0, 'updated_at' => now()],
                );
            }

            ClientEmail::espejar($this->clientId);
        });

        Audit::log('Editó el cliente '.$this->client->fullName(), $this->client);

        session()->flash('client_success', 'Cliente actualizado correctamente.');

        return redirect()->route('clients.index');
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        Client::findOrFail($id)->update(['status' => 'inactive']);
        Audit::log("Desactivó el cliente #{$id}");
        session()->flash('client_success', 'Cliente desactivado correctamente.');
        $this->redirectRoute('clients.index');
    }

    public function render()
    {
        return view('livewire.clients.edit');
    }
}
