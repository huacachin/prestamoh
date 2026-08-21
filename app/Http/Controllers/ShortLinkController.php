<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;

/**
 * Acortador propio: /s/{code} → redirige al destino guardado (links de recibo
 * para WhatsApp). Público sin firma: el destino ES la URL firmada — la
 * seguridad viaja dentro del destino, no en el código corto.
 * (Era un closure en routes/web.php; como controlador la ruta es cacheable.)
 */
class ShortLinkController extends Controller
{
    public function __invoke(string $code)
    {
        $link = ShortLink::where('code', $code)->firstOrFail();
        $link->increment('hits');

        return redirect()->away($link->destino);
    }
}
