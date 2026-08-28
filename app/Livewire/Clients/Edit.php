<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use App\Support\Audit;
use App\Support\TiposCredito;
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

    public string $provincia = 'LIMA';

    public ?string $departamento = null;

    public ?string $nacionalidad = null;

    public string $email = '';

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
            'provincia' => 'required|in:'.implode(',', array_keys(Create::PROVINCIAS)),
            'departamento' => 'nullable|string|max:100',
            'nacionalidad' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'ocupacion' => 'required|in:'.implode(',', array_keys(Create::OCUPACIONES)),
            'estado_civil' => 'required|in:'.implode(',', array_keys(Create::ESTADOS_CIVILES)),
            'referencia' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:100',
            'capital' => 'nullable|numeric|min:0',
            'telefono_secundario' => 'nullable|string|max:20',
            'celular1' => 'nullable|string|max:20',
            'celular2' => 'nullable|string|max:20',
            // Acepta las opciones vigentes y el valor histórico ya guardado
            'zona' => 'nullable|string|in:'.implode(',', TiposCredito::paraValor($this->zona)),
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
        $this->provincia = isset(Create::PROVINCIAS[(string) $c->provincia]) ? (string) $c->provincia : 'LIMA';
        $this->departamento = $c->departamento;
        $this->nacionalidad = $c->nacionalidad;
        $this->email = (string) ($c->email ?? '');
        $this->ocupacion = isset(Create::OCUPACIONES[(string) $c->ocupacion]) ? (string) $c->ocupacion : 'transportista';
        $this->estado_civil = isset(Create::ESTADOS_CIVILES[(string) $c->estado_civil]) ? (string) $c->estado_civil : 'soltero';
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
            'nacionalidad' => filled($this->nacionalidad) ? mb_strtoupper(trim($this->nacionalidad)) : null,
            'email' => trim($this->email) ?: null,
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

        $this->client->update($data);

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
