<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Login/logout como controlador (antes eran closures en routes/web.php):
 * las rutas closure no se pueden cachear con route:cache.
 */
class AuthController extends Controller
{
    public function logout(Request $request)
    {
        Audit::log('Cerró sesión'); // antes del logout, para que quede el usuario
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
