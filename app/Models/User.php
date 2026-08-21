<?php

namespace App\Models;

use App\Support\PermisosVista;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable {
        assignRole as protected spatieAssignRole;
        syncRoles as protected spatieSyncRoles;
    }

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'document_type',
        'document_number',
        'phone',
        'headquarter_id',
        'status',
        'nivel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function headquarter()
    {
        return $this->belongsTo(Headquarter::class);
    }

    /**
     * Al asignar/cambiar rol se siembran los checks de visualización de
     * /users/{id}/perms según la matriz del rol. Si el rol no cambió, los
     * checks personalizados se respetan (no se resiembran).
     */
    public function assignRole(...$roles)
    {
        return $this->conSincronizacionDeVista(fn () => $this->spatieAssignRole(...$roles));
    }

    public function syncRoles(...$roles)
    {
        return $this->conSincronizacionDeVista(fn () => $this->spatieSyncRoles(...$roles));
    }

    private function conSincronizacionDeVista(callable $accion)
    {
        $antes = $this->roles()->pluck('name')->sort()->values()->all();
        $resultado = $accion();
        $this->unsetRelation('roles');
        if ($this->roles()->pluck('name')->sort()->values()->all() !== $antes) {
            $this->load('roles.permissions');
            PermisosVista::sincronizarConRol($this);
            $this->unsetRelation('permissions');
        }

        return $resultado;
    }
}
