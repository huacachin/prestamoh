<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Todo ícono `ti-*` usado en las vistas debe existir en el set de Tabler
 * que sirve el proyecto (05/09).
 *
 * Un ícono inexistente no falla ni avisa: el botón se pinta VACÍO. Así
 * estuvo el de "Re-activar" en /users (usaba ti-restore, que no existe en
 * esta versión) y otros 7 repartidos por el sistema.
 */
class IconosExistentesTest extends TestCase
{
    /** @return array<string> nombres disponibles en el CSS del set */
    private function disponibles(): array
    {
        $css = file_get_contents(public_path('assets/vendor/tabler-icons/tabler-icons.css'));
        preg_match_all('/\.(ti-[a-z0-9-]+):before/', $css, $m);

        return array_unique($m[1]);
    }

    public function test_ningun_icono_de_las_vistas_falta_en_el_set(): void
    {
        $disponibles = $this->disponibles();
        $this->assertGreaterThan(1000, count($disponibles), 'el CSS del set debe estar presente');

        $rotos = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }
            $contenido = file_get_contents($archivo->getPathname());
            // `ti ti-algo` con nombre literal; se ignoran los que Blade arma
            // en tiempo de render (ti-trending-{{ ... }}).
            preg_match_all('/\bti\s+(ti-[a-z0-9-]+)(?![-a-z0-9{])/', $contenido, $m);
            foreach ($m[1] as $icono) {
                if (! in_array($icono, $disponibles, true)) {
                    $rotos[] = $icono.' en '.str_replace(resource_path('views').'/', '', $archivo->getPathname());
                }
            }
        }

        $this->assertSame([], array_values(array_unique($rotos)),
            'Estos íconos no existen en el set y se pintan como un botón vacío.');
    }
}
