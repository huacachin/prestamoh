<?php

namespace App\Support\Auditoria;

/**
 * Para los componentes Livewire con formularios importantes (08/10/2026): la
 * propiedad `capturaAuditoria` recibe la foto que manda $captura(...) desde el
 * navegador junto con la acción. Livewire corre el hook updated ANTES de la
 * llamada al método, así que la captura ya está pendiente cuando la acción
 * escribe su auditoría; y se vacía en el acto para que el data URL no viaje
 * de vuelta ni quede en el snapshot.
 */
trait ConCapturaDeAuditoria
{
    public ?string $capturaAuditoria = null;

    public function updatedCapturaAuditoria($valor): void
    {
        CapturaAuditoria::pendiente(is_string($valor) && $valor !== '' ? $valor : null);
        $this->capturaAuditoria = null;
    }
}
