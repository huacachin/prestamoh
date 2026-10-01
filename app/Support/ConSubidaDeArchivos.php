<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * WithFileUploads de Livewire con red de seguridad para la subida que llega
 * vacía.
 *
 * Si la conexión del usuario se corta mientras el navegador envía el archivo,
 * Apache (RequestReadTimeout) deja de esperar el cuerpo y PHP sigue sin ningún
 * archivo. El endpoint de subida de Livewire 4.2.4 no exige que llegue al
 * menos uno: responde 200 con una lista de rutas vacía, el JS se la pasa a
 * _finishUpload y este hace $tmpPath[0] sin comprobar → 500 "Undefined array
 * key 0" (30/09/2026 22:02, adjuntando el voucher de un cobro).
 *
 * Aquí la lista vacía recibe el mismo trato que Livewire da a una subida
 * rechazada (_uploadErrored): se avisa al JS para que suelte el estado
 * "Subiendo…" y se lanza un error de validación sobre la propiedad, que la
 * vista muestra con @error.
 */
trait ConSubidaDeArchivos
{
    use WithFileUploads {
        _finishUpload as protected finishUploadDeLivewire;
    }

    public function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
    {
        if (empty($tmpPath)) {
            Log::warning('Subida de archivo sin contenido: el servidor no recibió el archivo', [
                'componente' => static::class,
                'propiedad' => $name,
                'usuario' => auth()->id(),
            ]);

            $this->dispatch('upload:errored', name: $name)->self();

            throw ValidationException::withMessages([
                $name => 'No se recibió el archivo: la conexión se cortó durante la subida. Inténtalo de nuevo.',
            ]);
        }

        $this->finishUploadDeLivewire($name, $tmpPath, $isMultiple, $append);
    }
}
