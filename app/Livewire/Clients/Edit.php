<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Client $client;
    public int $clientId;

    public string $nombre = '';
    public string $apellido_pat = '';
    public string $apellido_mat = '';
    public string $tipo_documento = 'DNI';
    public string $documento = '';
    public ?string $fecha_nacimiento = null;
    public string $sexo = 'M';
    public ?string $email = null;
    public ?string $telefono_fijo = null;
    public ?string $celular1 = null;
    public ?string $celular2 = null;

    public ?string $direccion = null;
    public ?string $referencia = null;
    public ?string $distrito = null;
    public ?string $provincia = null;
    public ?string $departamento = null;
    public ?string $zona = null;

    public ?string $contacto_emergencia = null;
    public ?string $telefono_contacto = null;

    public ?string $banco_haberes = null;
    public ?string $cuenta_haberes = null;
    public ?string $banco_cts = null;
    public ?string $cuenta_cts = null;
    public ?string $afp = null;
    public ?string $cussp = null;

    public ?string $observaciones = null;
    public ?int $asesor_id = null;
    public ?int $headquarter_id = null;
    public $imagen = null;
    public ?string $imagen_actual = null;

    public $asesores;
    public $headquarters;

    protected function rules()
    {
        return [
            'nombre'       => 'required|string|max:100',
            'apellido_pat' => 'required|string|max:100',
            'apellido_mat' => 'nullable|string|max:100',
            'tipo_documento' => 'required|in:DNI,RUC,CE',
            'documento'    => 'required|string|max:20|unique:clients,documento,' . $this->clientId,
            'fecha_nacimiento' => 'nullable|date',
            'sexo'         => 'required|in:M,F',
            'email'        => 'nullable|email|max:255',
            'celular1'     => 'nullable|string|max:20',
            'direccion'    => 'nullable|string|max:255',
            'asesor_id'    => 'nullable|exists:users,id',
            'headquarter_id' => 'nullable|exists:headquarters,id',
            'imagen'       => 'nullable|image|max:3072',
        ];
    }

    public function mount(int $id)
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;

        $this->asesores = User::where('status', 'active')->get(['id', 'name']);
        $this->headquarters = Headquarter::where('status', 'active')->get(['id', 'name']);

        $this->nombre        = $this->client->nombre ?? '';
        $this->apellido_pat  = $this->client->apellido_pat ?? '';
        $this->apellido_mat  = $this->client->apellido_mat ?? '';
        $this->tipo_documento = $this->client->tipo_documento ?? 'DNI';
        $this->documento     = $this->client->documento ?? '';
        $this->fecha_nacimiento = $this->client->fecha_nacimiento?->format('Y-m-d');
        $this->sexo          = $this->client->sexo ?? 'M';
        $this->email         = $this->client->email;
        $this->telefono_fijo = $this->client->telefono_fijo;
        $this->celular1      = $this->client->celular1;
        $this->celular2      = $this->client->celular2;
        $this->direccion     = $this->client->direccion;
        $this->referencia    = $this->client->referencia;
        $this->distrito      = $this->client->distrito;
        $this->provincia     = $this->client->provincia;
        $this->departamento  = $this->client->departamento;
        $this->zona          = $this->client->zona;
        $this->contacto_emergencia = $this->client->contacto_emergencia;
        $this->telefono_contacto   = $this->client->telefono_contacto;
        $this->banco_haberes = $this->client->banco_haberes;
        $this->cuenta_haberes = $this->client->cuenta_haberes;
        $this->banco_cts     = $this->client->banco_cts;
        $this->cuenta_cts    = $this->client->cuenta_cts;
        $this->afp           = $this->client->afp;
        $this->cussp         = $this->client->cussp;
        $this->observaciones = $this->client->observaciones;
        $this->asesor_id     = $this->client->asesor_id;
        $this->headquarter_id = $this->client->headquarter_id;
        $this->imagen_actual = $this->client->imagen;
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        Client::findOrFail($id)->update(['status' => 'inactive']);
        session()->flash('client_success', 'Cliente desactivado correctamente.');
        $this->redirectRoute('clients.index');
    }

    public function update()
    {
        $this->validate();

        $data = [
            'nombre'        => $this->nombre,
            'apellido_pat'  => $this->apellido_pat,
            'apellido_mat'  => $this->apellido_mat,
            'tipo_documento' => $this->tipo_documento,
            'documento'     => $this->documento,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'sexo'          => $this->sexo,
            'email'         => $this->email,
            'telefono_fijo' => $this->telefono_fijo,
            'celular1'      => $this->celular1,
            'celular2'      => $this->celular2,
            'direccion'     => $this->direccion,
            'referencia'    => $this->referencia,
            'distrito'      => $this->distrito,
            'provincia'     => $this->provincia,
            'departamento'  => $this->departamento,
            'zona'          => $this->zona,
            'contacto_emergencia' => $this->contacto_emergencia,
            'telefono_contacto'   => $this->telefono_contacto,
            'banco_haberes' => $this->banco_haberes,
            'cuenta_haberes' => $this->cuenta_haberes,
            'banco_cts'     => $this->banco_cts,
            'cuenta_cts'    => $this->cuenta_cts,
            'afp'           => $this->afp,
            'cussp'         => $this->cussp,
            'observaciones' => $this->observaciones,
            'asesor_id'     => $this->asesor_id,
            'headquarter_id' => $this->headquarter_id,
        ];

        if ($this->imagen) {
            $data['imagen'] = $this->imagen->store('clients', 'public');
        }

        $this->client->update($data);

        session()->flash('client_success', 'Cliente actualizado correctamente.');
        return redirect()->route('clients.index');
    }

    public function render()
    {
        return view('livewire.clients.edit');
    }
}
