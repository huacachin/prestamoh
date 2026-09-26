<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public string $username = '';

    public string $pwd = '';

    public ?string $email = null;

    public string $document_type = 'DNI';

    public string $document_number = '';

    public string $phone = '';

    public ?int $headquarter_id = null;

    public ?int $selectedRoleId = null;

    public $headquarters;

    public $roles = [];

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', Rule::unique('users', 'username')],
            // NOT NULL en la tabla users: si se deja vacío el insert revienta
            // con 1048 y el cajero ve un 500 (pasó el 14/09). Se pide aquí.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'pwd' => ['required', 'string', 'min:8'],
            'document_type' => ['required', 'string', 'max:3'],
            'document_number' => ['required', 'string', 'max:11', Rule::unique('users', 'document_number')->where(fn ($q) => $q->where('document_type', $this->document_type))],
            'phone' => ['required', 'string', 'max:15'],
            // Obligatoria (14/09): sin sede, una docena de pantallas (pagos,
            // caja, clientes) asumen en silencio la sede 1 y lo registrado
            // queda contabilizado donde no es.
            'headquarter_id' => ['required', 'integer', 'exists:headquarters,id'],
            'selectedRoleId' => ['nullable', 'integer', Rule::in(RoleSetupSeeder::asignableRoles()->pluck('id'))],
        ];
    }

    protected $validationAttributes = [
        'document_number' => 'número de documento',
        'pwd' => 'contraseña',
    ];

    public function mount()
    {
        if (! auth()->user()?->can('configuracion.usuarios')) {
            abort(403);
        }

        $this->headquarters = Headquarter::where('status', 'active')
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
        // Viene marcada la sede principal: hoy solo hay una (Huacachín) y
        // dejar "— Seleccionar —" solo servía para olvidarla.
        $this->headquarter_id ??= $this->headquarters->first()?->id;
        // Catálogo completo: los no-asignables se pintan deshabilitados
        $this->roles = RoleSetupSeeder::orderedRoles();
    }

    /**
     * Livewire no pasa por el TrimStrings de las peticiones normales, así que
     * un espacio de más al tipear se guardaba tal cual ("Marvin "): ensucia
     * exportes y comparaciones, y rompería el ingreso si algún día la base
     * deja de ignorar los espacios finales al comparar.
     */
    private function recortarEspacios(): void
    {
        $this->name = trim($this->name);
        $this->username = trim($this->username);
        $this->email = $this->email === null ? null : trim($this->email);
        $this->document_number = trim($this->document_number);
        $this->phone = trim($this->phone);
    }

    public function clean(): void
    {
        $this->reset(['name', 'username', 'pwd', 'email', 'document_type', 'document_number', 'phone', 'headquarter_id', 'selectedRoleId']);
        // Limpiar el formulario no debe dejar la sede vacía.
        $this->headquarter_id = $this->headquarters->first()?->id;
    }

    public function save()
    {
        $this->recortarEspacios();
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => Hash::make($this->pwd),
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'phone' => $this->phone,
            'headquarter_id' => $this->headquarter_id,
        ]);

        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
            if ($roleName) {
                $user->syncRoles([$roleName]);
            }
        }

        session()->flash('user_success', 'Usuario creado correctamente.');

        return redirect()->route('settings.users.index');
    }

    public function render()
    {
        return view('livewire.users.create');
    }
}
