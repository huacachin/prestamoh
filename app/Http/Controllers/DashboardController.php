<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        // Sin permiso de dashboard (analista-creditos) se aterriza en su
        // listado de créditos: cubre el redirect post-login y el logo del sidebar.
        if (! (auth()->user()?->can('dashboard') ?? false)) {
            return redirect()->route('credits.index');
        }

        return view('dashboard.index');
    }
}
