<?php

namespace App\Livewire\Users;

use App\Models\Permission;
use App\Models\User;
use App\Support\PermisosVista;
use Database\Seeders\RoleSetupSeeder;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

class Perms extends Component
{
    public User $user;

    public ?string $permsUserName = null;

    public ?int $selectedRoleId = null;

    public array $selectedPermissionNames = [];

    public array $aclGroups = [];

    public $roles = [];

    public bool $canEdit = true;

    public function mount(int $id)
    {
        if (! auth()->user()?->can('usuarios.gestionar-permisos')) {
            abort(403);
        }

        $this->user = User::with('roles')->findOrFail($id);

        $this->permsUserName = $this->user->name;
        $this->roles = RoleSetupSeeder::orderedRoles();
        $this->selectedRoleId = $this->user->roles()->value('id');

        // Director: no editable (es el super-rol homologado).
        $editedRole = $this->user->roles->first()?->name;
        if ($editedRole === 'director') {
            $this->selectedPermissionNames = Permission::where('guard_name', 'web')->pluck('name')->toArray();
            $this->canEdit = false;
        } else {
            $this->selectedPermissionNames = $this->user->permissions()->pluck('name')->toArray();
        }

        $this->buildAclGroups();
    }

    /**
     * Construye los grupos en base a la estructura EXACTA del sidebar.
     * Permisos en BD que no estén en este mapa quedan ocultos del form
     * (siguen en la BD asignados a quienes ya los tienen, pero no se exponen).
     */
    private function buildAclGroups(): void
    {
        // Estructura idéntica al sidebar (resources/views/layout/sidebar.blade.php)
        $sidebarStructure = [
            [
                'type' => 'single',
                'key' => 'dashboard',
                'title' => 'Panel De Control',
                'permissions' => [
                    ['perm' => 'dashboard', 'label' => 'Panel De Control'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'configuracion',
                'title' => 'Configuración',
                'permissions' => [
                    ['perm' => 'configuracion.usuarios',   'label' => 'Usuarios'],
                    ['perm' => 'configuracion.sucursales', 'label' => 'Sucursales'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'registro',
                'title' => 'Registro',
                'permissions' => [
                    ['perm' => 'registro.activar',          'label' => 'Activar Prestamos'],
                    ['perm' => 'registro.estado',           'label' => 'Cambiar Estado'],
                    ['perm' => 'clientes',                  'label' => 'Cliente'],
                    ['perm' => 'registro.cesados',          'label' => 'Cliente Cesados'],
                    ['perm' => 'configuracion.conceptos',   'label' => 'Conceptos Fijos'],
                    ['perm' => 'registro.eliminar-masivo',  'label' => 'Eliminar Masivo'],
                    ['perm' => 'pagos',                     'label' => 'Pagos/Credito'],
                    ['perm' => 'creditos',                  'label' => 'Prestamo'],
                    ['perm' => 'configuracion.tipo-cambio', 'label' => 'Tipo de Cambio'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'caja',
                'title' => 'Caja',
                'permissions' => [
                    ['perm' => 'caja.apertura', 'label' => 'Apertura Caja'],
                    ['perm' => 'caja.ingresos', 'label' => 'Ingreso'],
                    ['perm' => 'caja.egresos',  'label' => 'Egreso'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'reportes',
                'title' => 'Reportes',
                'permissions' => [
                    ['perm' => 'reportes.credito-diario',      'label' => 'Reporte Credito D.'],
                    ['perm' => 'reportes.credito-mensual',     'label' => 'Reporte Credito M.'],
                    ['perm' => 'reportes.credito-semanal',     'label' => 'Reporte Credito S.'],
                    ['perm' => 'reportes.asesor',              'label' => 'Reporte de Asesor'],
                    ['perm' => 'reportes.pagos',               'label' => 'Reporte de Pago'],
                    ['perm' => 'reportes.caja-estadistica',    'label' => 'Rep. Estad. Caja M.A.'],
                    ['perm' => 'reportes.credito-estadistica', 'label' => 'Rep. Estad. Crédito'],
                    ['perm' => 'reportes.caja-general-1',      'label' => 'Rep. General Caja 1'],
                    ['perm' => 'reportes.caja-general-2',      'label' => 'Rep. General Caja 2'],
                    ['perm' => 'reportes.caja-general-3',      'label' => 'Rep. General Caja 3'],
                    ['perm' => 'reportes.cartera',             'label' => 'Resumen de Créditos'],
                    ['perm' => 'reportes.morosidad',           'label' => 'Pendientes x Cobrar'],
                    ['perm' => 'reportes.cancelados',          'label' => 'Resumen de Cancelados'],
                    ['perm' => 'reportes.simulador',           'label' => 'Simulacro de Crédito'],
                ],
            ],
        ];

        // Sólo incluir items cuyo permiso EXISTA en BD (defensa contra cambios de schema)
        $permsExistentes = Permission::where('guard_name', 'web')->pluck('name')->all();
        $groups = [];

        foreach ($sidebarStructure as $g) {
            $items = [];
            foreach ($g['permissions'] as $p) {
                if (in_array($p['perm'], $permsExistentes, true)) {
                    $items[] = ['key' => $p['perm'], 'label' => $p['label']];
                }
            }
            if (empty($items)) {
                continue;
            }

            $groups[$g['key']] = [
                'type' => $g['type'],
                'title' => $g['title'],
                'items' => $items,
            ];
        }

        $this->aclGroups = $groups;
    }

    public function selectGroup(string $groupKey): void
    {
        if (! isset($this->aclGroups[$groupKey])) {
            return;
        }
        $keys = array_column($this->aclGroups[$groupKey]['items'], 'key');
        $this->selectedPermissionNames = array_values(array_unique(array_merge($this->selectedPermissionNames, $keys)));
    }

    public function deselectGroup(string $groupKey): void
    {
        if (! isset($this->aclGroups[$groupKey])) {
            return;
        }
        $keys = array_column($this->aclGroups[$groupKey]['items'], 'key');
        $this->selectedPermissionNames = array_values(array_diff($this->selectedPermissionNames, $keys));
    }

    public function savePerms(): void
    {
        if (! auth()->user()?->can('usuarios.gestionar-permisos')) {
            abort(403);
        }

        $user = $this->user;

        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $user->syncRoles($roleName ? [$roleName] : []);

        // Los checkboxes son la visibilidad de módulos; los permisos finos
        // directos (fuera de la lista de vista) se conservan tal cual.
        $names = Permission::whereIn('name', $this->selectedPermissionNames)->pluck('name');
        $finos = $user->permissions()->pluck('name')->diff(PermisosVista::LISTA);
        $user->syncPermissions($names->merge($finos)->unique()->values()->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('successAlert', ['message' => 'Rol & permisos actualizados']);
    }

    public function render()
    {
        return view('livewire.users.perms');
    }
}
