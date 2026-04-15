<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

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
            'name'            => ['required', 'string', 'max:255'],
            'username'        => ['required', 'string', 'min:3', 'max:64', Rule::unique('users', 'username')],
            'email'           => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'pwd'             => ['required', 'string', 'min:8'],
            'document_type'   => ['required', 'string', 'max:3'],
            'document_number' => ['required', 'string', 'max:11', Rule::unique('users', 'document_number')->where(fn ($q) => $q->where('document_type', $this->document_type))],
            'phone'           => ['required', 'string', 'max:15'],
            'headquarter_id'  => ['nullable', 'integer', 'exists:headquarters,id'],
            'selectedRoleId'  => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }

    protected $validationAttributes = [
        'document_number' => 'número de documento',
        'pwd' => 'contraseña',
    ];

    public function mount()
    {
        if (!auth()->user()?->hasAnyRole('superusuario', 'administrador')) {
            abort(403);
        }

        $this->headquarters = Headquarter::where('status', 'active')->get(['id', 'name']);
        $this->roles = Role::all(['id', 'name']);
    }

    public function clean(): void
    {
        $this->reset(['name', 'username', 'pwd', 'email', 'document_type', 'document_number', 'phone', 'headquarter_id', 'selectedRoleId']);
    }

    public function save()
    {
        $this->validate();

        $user = User::create([
            'name'            => $this->name,
            'username'        => $this->username,
            'email'           => $this->email,
            'password'        => Hash::make($this->pwd),
            'document_type'   => $this->document_type,
            'document_number' => $this->document_number,
            'phone'           => $this->phone,
            'headquarter_id'  => $this->headquarter_id,
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
