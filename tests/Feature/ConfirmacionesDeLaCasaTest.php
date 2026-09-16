<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Los diálogos del sistema son SIEMPRE los de la casa (SweetAlert), nunca
 * los nativos del navegador (05/09).
 *
 * `wire:confirm` de Livewire usa window.confirm() —el cuadro gris del
 * navegador— y `alert()` lo mismo. Este test evita que vuelvan a colarse:
 * el reemplazo es `data-confirmar="…"` (interceptado en custom.js) y
 * `avisar('…')`.
 */
class ConfirmacionesDeLaCasaTest extends TestCase
{
    private function blades(): array
    {
        $archivos = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $archivos[] = $f->getPathname();
            }
        }

        return $archivos;
    }

    public function test_ninguna_vista_usa_el_confirm_nativo(): void
    {
        $culpables = [];
        foreach ($this->blades() as $ruta) {
            if (str_contains(file_get_contents($ruta), 'wire:confirm')) {
                $culpables[] = str_replace(resource_path('views').'/', '', $ruta);
            }
        }

        $this->assertSame([], $culpables,
            'Usa data-confirmar="…" en vez de wire:confirm (el confirm() nativo del navegador).');
    }

    public function test_ninguna_vista_usa_el_alert_nativo(): void
    {
        $culpables = [];
        foreach ($this->blades() as $ruta) {
            $contenido = file_get_contents($ruta);
            // alert( que NO sea successAlert/errorAlert/alertError/avisar
            if (preg_match('/(?<![.\w])alert\s*\(/', $contenido)) {
                $culpables[] = str_replace(resource_path('views').'/', '', $ruta);
            }
        }

        $this->assertSame([], $culpables,
            'Usa avisar("…") en vez de alert() (el cuadro nativo del navegador).');
    }

    /** El helper y el interceptor tienen que existir en custom.js. */
    public function test_custom_js_trae_los_reemplazos(): void
    {
        $js = file_get_contents(public_path('assets/js/custom.js'));
        $this->assertStringContainsString('function avisar(', $js);
        $this->assertStringContainsString("closest('[data-confirmar]')", $js);
        // Y el "Eliminado!" optimista no debe volver: lo canta el servidor.
        $this->assertStringNotContainsString('El registro se eliminado correctamente', $js);
    }
}
