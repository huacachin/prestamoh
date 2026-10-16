<?php

namespace App\Http\Controllers;

/** Mi perfil (16/10/2026): datos propios, contraseña y sesiones activas del usuario logueado. */
class PerfilController extends Controller
{
    public function index()
    {
        return view('perfil.index');
    }
}
