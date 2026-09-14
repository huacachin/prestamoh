<?php

namespace App\Http\Controllers;

use App\Support\MenuLateral;

class DashboardController extends Controller
{
    public function index()
    {
        // Sin permiso de dashboard se aterriza en el PRIMER módulo que el
        // usuario sí pueda abrir (orden del menú). Antes apuntaba fijo a
        // credits.index y el rol area-legal, que no tiene créditos, rebotaba
        // entre pantallas prohibidas (14/09). Cubre el redirect post-login y
        // el logo del sidebar.
        if (! (auth()->user()?->can('dashboard') ?? false)) {
            return redirect()->route(MenuLateral::rutaInicio(auth()->user()));
        }

        return view('dashboard.index');
    }
}
