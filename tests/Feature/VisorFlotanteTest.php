<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gallery;
use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): las previsualizaciones de vouchers (y todo lo que usa el
 * partial _lightbox) se levantan como VENTANA FLOTANTE en escritorio: sin fondo
 * oscuro, arrastrable solo desde la cabecera, redimensionable, con posición
 * recordada; en celular sigue a pantalla completa. Los atajos de teclado pasan
 * al partial (antes vivían en cada pantalla: ahora duplicarlos haría saltar dos
 * fotos por pulsación), y las flechas no actúan mientras se escribe.
 */
class VisorFlotanteTest extends TestCase
{
    use RefreshDatabase;

    private function paginaComo(array $permisos, string $ruta): string
    {
        $user = User::factory()->create(['username' => 'visor-tester-'.uniqid()]);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo($permisos);
        $this->actingAs($user);

        return $this->get($ruta)->assertOk()->getContent();
    }

    public function test_el_visor_de_caja_es_una_ventana_flotante_con_cabecera_arrastrable(): void
    {
        $html = $this->paginaComo(['caja.ingresos'], route('cash.incomes'));

        // El partial sigue siendo el mismo (los tests viejos buscan class="huac-lb") y ahora anida el componente.
        $this->assertStringContainsString('x-data="visorFlotante" x-show="open" x-cloak x-transition.opacity', $html);
        $this->assertStringContainsString('class="huac-lb" :class="{ \'huac-lb--flotante\': flotante }"', $html);
        // Sin fondo que cierre al hacer clic fuera cuando flota: la página sigue viva debajo.
        $this->assertStringContainsString('@click.self="flotante || close()"', $html);
        // Solo la cabecera arrastra (Pointer Events: mouse y dedo).
        $this->assertStringContainsString('<div class="huac-lb__cabecera"', $html);
        $this->assertStringContainsString('@pointerdown="iniciarArrastre($event)" @pointermove="arrastrar($event)"', $html);
        $this->assertStringContainsString('@pointerup="soltar($event)" @pointercancel="soltar($event)"', $html);
        $this->assertStringContainsString('<div class="huac-lb__ventana" x-ref="ventana" tabindex="-1" @click.stop>', $html);
        // CSS: en escritorio la capa no bloquea la página y la ventana flota y se redimensiona.
        $this->assertStringContainsString('.huac-lb--flotante { background: transparent; pointer-events: none;', $html);
        $this->assertStringContainsString('.huac-lb--flotante .huac-lb__ventana { position: fixed; pointer-events: auto;', $html);
        $this->assertStringContainsString('resize: both;', $html);
        // El script del componente va en la capa, con versión por fecha del archivo.
        $this->assertMatchesRegularExpression('#assets/js/ventanas-flotantes\.js\?v=\d+#', $html);
    }

    public function test_los_atajos_viven_en_el_partial_una_sola_vez_y_las_flechas_respetan_los_campos(): void
    {
        $html = $this->paginaComo(['caja.ingresos'], route('cash.incomes'));

        $this->assertStringContainsString('@keydown.escape.window="open && close()"', $html);
        $this->assertStringContainsString('@keydown.arrow-right.window="open && !escribiendo($event) && next()"', $html);
        $this->assertStringContainsString('@keydown.arrow-left.window="open && !escribiendo($event) && prev()"', $html);
        // Ya no están duplicados en la raíz de la pantalla (antes: dos saltos por pulsación).
        $this->assertStringNotContainsString('"open && next()"', $html);
        $this->assertStringNotContainsString('"open && prev()"', $html);
        $this->assertSame(
            substr_count($html, 'x-data="visorFlotante"'),
            substr_count($html, '@keydown.escape.window="open && close()"'),
            'un juego de atajos por visor, ni uno más'
        );

        // Lo mismo en las otras pantallas que incluyen el visor.
        foreach ([
            [['caja.egresos'], route('cash.expenses')],
        ] as [$permisos, $ruta]) {
            $otro = $this->paginaComo($permisos, $ruta);
            $this->assertStringNotContainsString('"open && next()"', $otro, $ruta);
            $this->assertStringContainsString('!escribiendo($event) && next()', $otro, $ruta);
        }
        foreach (['audit/index', 'cash/expense-gallery', 'cash/income-gallery', 'clients/gallery'] as $vista) {
            $fuente = file_get_contents(resource_path("views/livewire/{$vista}.blade.php"));
            $this->assertStringNotContainsString('@keydown.', $fuente, "{$vista}: los atajos ya no van en la raíz");
            $this->assertStringContainsString("@include('livewire.cash.partials._lightbox')", $fuente, $vista);
            $this->assertStringNotContainsString('.huac-lb__stage', $fuente, "{$vista}: sin copias propias del visor viejo");
        }
    }

    public function test_la_galeria_de_adjuntos_del_cliente_usa_el_mismo_visor(): void
    {
        // Antes tenía su propia copia del lightbox (misma clase, otro HTML): ahora comparte el partial.
        $user = User::factory()->create(['username' => 'visor-galeria']);
        $this->actingAs($user);
        $cliente = Client::create([
            'expediente' => '951', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Adjuntos',
            'tipo_documento' => 'DNI', 'documento' => '33445577', 'sexo' => 'M', 'status' => 'active', 'asesor_id' => $user->id,
        ]);
        ClientAttachment::create([
            'client_id' => $cliente->id, 'filename' => 'v.jpg', 'original_name' => 'voucher.jpg', 'path' => "clients/{$cliente->id}/v.jpg",
            'thumb_path' => null, 'mime' => 'image/jpeg', 'size' => 10, 'uploaded_by' => $user->id,
        ]);

        Livewire::test(Gallery::class, ['id' => $cliente->id])
            ->assertSeeHtml('x-data="visorFlotante"')
            ->assertSeeHtml('class="huac-lb__cabecera"')
            ->assertSeeHtml('@click="show(0)"')
            ->assertDontSeeHtml('huac-lb__stage');
    }

    public function test_el_html_del_visor_llega_entero_al_navegador(): void
    {
        $html = $this->paginaComo(['caja.ingresos'], route('cash.incomes'));

        // Ninguna comilla doble dentro de los atributos de Alpine (ya pasó con el modal de Anexo 2).
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $visor = $xp->query('//div[@class="huac-lb"]');
        $this->assertSame(1, $visor->length);
        $raiz = $visor->item(0);
        $this->assertSame('visorFlotante', $raiz->getAttribute('x-data'));
        $this->assertSame('open && !escribiendo($event) && next()', $raiz->getAttribute('@keydown.arrow-right.window'));
        $this->assertSame(1, $xp->query('.//div[@class="huac-lb__cabecera"]', $raiz)->length);
        $this->assertSame(1, $xp->query('.//img[@class="huac-lb__img" and @draggable="false"]', $raiz)->length);
    }

    public function test_el_script_recuerda_la_ventana_y_solo_flota_en_escritorio(): void
    {
        $js = file_get_contents(public_path('assets/js/ventanas-flotantes.js'));

        $this->assertStringContainsString("window.Alpine.data('visorFlotante', visorFlotante)", $js);
        $this->assertStringContainsString("window.Alpine.data('modalFlotante', modalFlotante)", $js);
        $this->assertStringContainsString("matchMedia('(min-width: 768px)')", $js, 'en celular sigue a pantalla completa');
        // Antony (10/10): "que siempre levante al centro, no al lado derecho". Solo el visor recuerda su tamaño.
        $this->assertStringContainsString('x: Math.max(0, Math.round((window.innerWidth - w) / 2))', $js);
        $this->assertStringContainsString('y: Math.max(MARGEN, Math.round((window.innerHeight - h) / 2))', $js);
        $this->assertStringContainsString("const CLAVE_VISOR = 'huac.visor-flotante';   // solo tamaño {w, h}", $js);
        $this->assertSame(1, substr_count($js, 'localStorage.setItem('), 'solo se guarda el tamaño del visor, ninguna posición');
        $modal = substr($js, strpos($js, 'function modalFlotante'), strpos($js, 'function registrar') - strpos($js, 'function modalFlotante'));
        $this->assertStringNotContainsString('localStorage', $modal, 'el modal no recuerda posición: siempre al centro');
        $this->assertStringContainsString("this.\$el.style.display = 'block';", $modal, 'se mide el diálogo para centrarlo en vertical');
        $this->assertStringContainsString('setPointerCapture(e.pointerId)', $js, 'el arrastre no se pierde al salir de la cabecera');
        $this->assertStringContainsString("document.body.classList.add('arrastrando-ventana')", $js, 'los iframes no se quedan con el puntero');
        $this->assertStringContainsString("e.target.closest('button, a, input, select, textarea')", $js, 'los botones de la cabecera no arrastran');
        $this->assertStringContainsString("['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)", $js, 'flechas solo si no se escribe');
        $this->assertStringContainsString('new ResizeObserver', $js, 'el tamaño redimensionado por la esquina también se guarda');
        $this->assertStringContainsString('{ backdrop: false, focus: false }', $js, 'el modal flotante no tapa ni secuestra el foco');
        $this->assertStringContainsString("document.body.classList.add('con-modal-flotante')", $js, 'la página sigue con scroll');
        $this->assertStringNotContainsString('$nextTick(() => this.alAbrir())', $js, 'Alpine retiene los ticks durante la transición de x-show');
    }

    /** La confirmación del cobro (/payments/create) es lo que Antony pidió primero: modal de Bootstrap flotante. */
    public function test_la_confirmacion_del_cobro_es_un_modal_flotante_arrastrable(): void
    {
        $vista = file_get_contents(resource_path('views/livewire/payments/create.blade.php'));
        $this->assertStringContainsString('id="ticketModal" tabindex="-1" aria-hidden="true" wire:ignore.self', $vista);
        $this->assertStringContainsString("x-data=\"modalFlotante('cobro')\"", $vista);
        $bloque = substr($vista, strpos($vista, 'id="ticketModal"'), 600);
        $this->assertStringNotContainsString('x-init=', $bloque, 'la instancia la crea el componente (backdrop/focus según pantalla)');
        // Bootstrap sigue abriendo y cerrando por los mismos eventos de Livewire.
        $this->assertStringContainsString('x-on:ticket-confirm.window="modal.show()"', $vista);
        $this->assertStringContainsString('x-on:ticket-close.window="modal.hide()"', $vista);
        $this->assertStringContainsString('<div class="modal-dialog modal-dialog-centered" style="max-width:420px;" x-ref="dialogo">', $vista);
        $this->assertStringContainsString('<div class="modal-header py-2"'."\n".'                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"', $vista);
        $this->assertStringContainsString('x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">', $vista);

        // El CSS va en la capa: la capa del modal no tapa la página y el diálogo queda fijo donde se deje.
        $html = $this->paginaComo(['caja.ingresos'], route('cash.incomes'));
        $this->assertStringContainsString('.modal.modal-flotante { pointer-events: none; }', $html);
        $this->assertStringContainsString('pointer-events: auto; position: fixed; margin: 0; display: block; min-height: 0;', $html);
        $this->assertStringContainsString('transform: none !important; transition: none;', $html, 'sin el translate de la animación de entrada: la posición es la guardada');
        $this->assertStringContainsString('.modal.modal-flotante .modal-header { cursor: grab;', $html);
        $this->assertStringContainsString('body.con-modal-flotante { overflow: visible !important; padding-right: 0 !important; }', $html);
        $this->assertStringContainsString('body.arrastrando-ventana iframe { pointer-events: none; }', $html);
    }

    /** "Ver recibo" (/payments/create, ficha del crédito y cronograma): el mismo modal, ahora partial y flotante. */
    public function test_el_modal_del_recibo_es_uno_solo_compartido_y_flotante(): void
    {
        $partial = file_get_contents(resource_path('views/livewire/payments/partials/_modal-recibo.blade.php'));
        $this->assertStringContainsString('<div class="modal fade" id="modal-recibo" tabindex="-1" wire:ignore x-data="modalFlotante(\'recibo\')">', $partial);
        $this->assertStringContainsString('<div class="modal-dialog modal-dialog-centered" style="max-width:430px;" x-ref="dialogo">', $partial);
        $this->assertStringContainsString('x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"', $partial);
        $this->assertStringContainsString('<iframe id="iframe-recibo" src="about:blank" allow="clipboard-write"', $partial);
        $this->assertStringContainsString('function abrirRecibo(url)', $partial, 'los botones onclick="abrirRecibo(...)" siguen igual');
        $this->assertStringContainsString("document.getElementById('iframe-recibo').src = 'about:blank';", $partial, 'al cerrar se descarga el iframe');

        foreach (['payments/create', 'credits/show', 'credits/schedule'] as $vista) {
            $fuente = file_get_contents(resource_path("views/livewire/{$vista}.blade.php"));
            $this->assertSame(1, substr_count($fuente, "@include('livewire.payments.partials._modal-recibo')"), $vista);
            $this->assertStringNotContainsString('id="modal-recibo"', $fuente, "{$vista}: sin copia propia del modal");
            $this->assertStringNotContainsString('function abrirRecibo', $fuente, "{$vista}: la función vive en el partial");
        }
    }
}
