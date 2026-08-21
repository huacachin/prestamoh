<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Login/logout como controlador (antes eran closures en routes/web.php):
 * las rutas closure no se pueden cachear con route:cache.
 */
class AuthController extends Controller
{
    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
