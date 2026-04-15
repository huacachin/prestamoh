<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    public User $user;

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

    public function mount(int $id)
    {
        if (!auth()->user()?->hasAnyRole('superusuario', 'administrador')) {
            abort(403);
        }

        $this->user = User::with('roles')->findOrFail($id);

        $this->headquarters = Headquarter::where('status', 'active')->get(['id', 'name']);
        $this->roles = Role::all(['id', 'name']);

        $this->name            = $this->user->name;
        $this->username        = $this->user->username;
        $this->email           = $this->user->email;
        $this->document_type   = $this->user->document_type ?? 'DNI';
        $this->document_number = $this->user->document_number ?? '';
        $this->phone           = $this->user->phone ?? '';
        $this->headquarter_id  = $this->user->headquarter_id;
        $this->selectedRoleId  = $this->user->roles()->value('id');
    }

    protected function rules()
    {
        $id = $this->user->id;

        return [
            'name'            => ['required', 'string', 'max:255'],
            'username'        => ['required', 'string', 'min:3', 'max:64', Rule::unique('users', 'username')->ignore($id)],
            'email'           => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'pwd'             => ['nullable', 'string', 'min:8'],
            'document_type'   => ['required', 'string', 'max:3'],
            'document_number' => ['required', 'string', 'max:11', Rule::unique('users', 'document_number')->ignore($id)->where(fn ($q) => $q->where('document_type', $this->document_type))],
            'phone'           => ['required', 'string', 'max:15'],
            'headquarter_id'  => ['nullable', 'integer', 'exists:headquarters,id'],
            'selectedRoleId'  => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }

    protected $validationAttributes = [
        'document_number' => 'número de documento',
        'pwd' => 'contraseña',
    ];

    public function update()
    {
        $this->validate();

        $payload = [
            'name'            => $this->name,
            'username'        => $this->username,
            'email'           => $this->email,
            'document_type'   => $this->document_type,
            'document_number' => $this->document_number,
            'phone'           => $this->phone,
            'headquarter_id'  => $this->headquarter_id,
        ];

        if (!empty($this->pwd)) {
            $payload['password'] = Hash::make($this->pwd);
        }

        $this->user->update($payload);

        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $this->user->syncRoles($roleName ? [$roleName] : []);

        session()->flash('user_success', 'Usuario actualizado correctamente.');
        return redirect()->route('settings.users.index');
    }

    public function render()
    {
        return view('livewire.users.edit');
    }
}
