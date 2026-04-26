<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    // Personal
    public string $nombre = '';
    public string $apellido_pat = '';
    public string $apellido_mat = '';
    public string $tipo_documento = 'DNI';
    public string $documento = '';
    public ?string $fecha_nacimiento = null;
    public string $sexo = 'M';
    public ?string $email = null;
    public ?string $giro = null;
    public ?string $celular1 = null;
    public ?string $celular2 = null;

    // Location
    public ?string $direccion = null;
    public ?string $referencia = null;
    public ?string $distrito = null;
    public ?string $provincia = null;
    public ?string $departamento = null;
    public ?string $zona = null;

    // Emergency
    public ?string $contacto_emergencia = null;
    public ?string $telefono_contacto = null;

    // Banking
    public ?string $banco_haberes = null;
    public ?string $cuenta_haberes = null;
    public ?string $banco_cts = null;
    public ?string $cuenta_cts = null;
    public ?string $afp = null;
    public ?string $cussp = null;

    // Extra
    public ?string $observaciones = null;
    public ?int $asesor_id = null;
    public ?int $headquarter_id = null;
    public $imagen = null;

    public $asesores;
    public $headquarters;

    protected $rules = [
        'nombre'       => 'required|string|max:100',
        'apellido_pat' => 'required|string|max:100',
        'apellido_mat' => 'nullable|string|max:100',
        'tipo_documento' => 'required|in:DNI,RUC,CE',
        'documento'    => 'required|string|max:20|unique:clients,documento',
        'fecha_nacimiento' => 'nullable|date',
        'sexo'         => 'required|in:M,F',
        'email'        => 'nullable|email|max:255',
        'celular1'     => 'nullable|string|max:20',
        'direccion'    => 'nullable|string|max:255',
        'asesor_id'    => 'nullable|exists:users,id',
        'headquarter_id' => 'nullable|exists:headquarters,id',
        'imagen'       => 'nullable|image|max:3072',
    ];

    public function mount()
    {
        $this->asesores = User::where('status', 'active')->get(['id', 'name']);
        $this->headquarters = Headquarter::where('status', 'active')->get(['id', 'name']);
        $this->headquarter_id = auth()->user()->headquarter_id;
        $this->asesor_id = auth()->id();
    }

    public function clean(): void
    {
        $this->reset([
            'nombre', 'apellido_pat', 'apellido_mat', 'tipo_documento', 'documento',
            'fecha_nacimiento', 'sexo', 'email', 'giro', 'celular1', 'celular2',
            'direccion', 'referencia', 'distrito', 'provincia', 'departamento', 'zona',
            'contacto_emergencia', 'telefono_contacto',
            'banco_haberes', 'cuenta_haberes', 'banco_cts', 'cuenta_cts', 'afp', 'cussp',
            'observaciones', 'imagen',
        ]);
        $this->resetErrorBag();
    }

    public function save()
    {
        $this->validate();

        $data = [
            'expediente'    => 'EXP-' . str_pad((Client::max('id') ?? 0) + 1, 6, '0', STR_PAD_LEFT),
            'nombre'        => $this->nombre,
            'apellido_pat'  => $this->apellido_pat,
            'apellido_mat'  => $this->apellido_mat,
            'tipo_documento' => $this->tipo_documento,
            'documento'     => $this->documento,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'sexo'          => $this->sexo,
            'email'         => $this->email,
            'giro'          => $this->giro,
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
            'status'        => 'active',
        ];

        if ($this->imagen) {
            $data['imagen'] = $this->imagen->store('clients', 'public');
        }

        Client::create($data);

        session()->flash('client_success', 'Cliente registrado correctamente.');
        return redirect()->route('clients.index');
    }

    public function render()
    {
        return view('livewire.clients.create');
    }
}
