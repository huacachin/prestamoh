<?php

namespace App\Support;

use App\Models\User;

/**
 * Permisos de VISUALIZACIÓN de módulos (los checkboxes de /users/{id}/perms,
 * espejo del sidebar). Para estos permisos manda el check por usuario:
 * desmarcado = no ve el módulo aunque su rol lo tenga (Gate::before en
 * AppServiceProvider). El director está exento (siempre ve todo).
 *
 * El resto de permisos (finos: caja.ver-todo, pagos.mora-manual, etc.)
 * conserva la unión rol + directos de Spatie.
 */
class PermisosVista
{
    public const LISTA = [
        'dashboard',
        'configuracion.usuarios', 'configuracion.sucursales',
        'registro.activar', 'registro.estado', 'clientes', 'registro.cesados',
        'configuracion.conceptos', 'registro.eliminar-masivo', 'pagos', 'creditos',
        'configuracion.tipo-cambio',
        'caja.apertura', 'caja.ingresos', 'caja.egresos',
        'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
        'reportes.asesor', 'reportes.pagos', 'reportes.caja-estadistica',
        'reportes.credito-estadistica', 'reportes.caja-general-1', 'reportes.caja-general-2',
        'reportes.caja-general-3', 'reportes.cartera', 'reportes.morosidad',
        'reportes.cancelados', 'reportes.simulador',
        'legal.garantias', 'legal.contratos', 'legal.notaria',
        'legal.judicial', 'legal.papeletas', 'legal.caja', 'legal.configuracion',
    ];

    /**
     * Los módulos que hoy se ofrecen en la matriz: la LISTA sin el Área Legal
     * mientras esté oculta (config legal.activo).
     *
     * @return list<string>
     */
    public static function visibles(): array
    {
        return array_values(array_filter(self::LISTA, fn (string $p) => config('legal.activo') || ! str_starts_with($p, 'legal.')));
    }

    /**
     * Cuántos módulos del menú ve el usuario (16/10/2026, listado de usuarios):
     * el director los ve todos (el Gate lo exime de los checks); el resto, los
     * que tiene marcados en /users/{id}/perms.
     */
    public static function modulosQueVe(User $user): int
    {
        $visibles = self::visibles();
        if ($user->hasRole('director')) {
            return count($visibles);
        }

        return $user->permissions->pluck('name')->intersect($visibles)->count();
    }

    /**
     * Siembra los checks de visualización del usuario según su rol actual
     * (matriz ∩ LISTA), conservando los permisos directos finos que tenga.
     * Se dispara al asignar/cambiar rol (overrides en User) y en el seeder.
     */
    public static function sincronizarConRol(User $user): void
    {
        if ($user->hasRole('director')) {
            return; // el director no depende de directos: el Gate lo exime
        }

        $delRol = $user->roles->flatMap(fn ($r) => $r->permissions->pluck('name'))->unique();
        $vista = $delRol->intersect(self::LISTA);
        $finosDirectos = $user->permissions()->pluck('name')->diff(self::LISTA);

        $user->syncPermissions($vista->merge($finosDirectos)->unique()->values()->all());
    }
}
