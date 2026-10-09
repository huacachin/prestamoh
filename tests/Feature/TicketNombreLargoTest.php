<?php

namespace Tests\Feature;

use App\Services\Printing\TicketPrinter;
use Tests\TestCase;

/**
 * 08/10/2026 (Antony): en las vistas previas de los tickets (modal de cobro,
 * recibo HTML y PDF) el nombre del cliente se salía del ancho. Ahora, si no
 * cabe al lado del rótulo, baja a la línea siguiente; y la impresora hace lo
 * mismo en vez de recortarlo con "…".
 */
class TicketNombreLargoTest extends TestCase
{
    public function test_la_impresora_baja_el_nombre_largo_y_lo_parte_al_ancho_sin_recortar(): void
    {
        $p = app(TicketPrinter::class);

        // Cabe: misma línea, valor a la derecha.
        $this->assertSame('Cliente:'.str_repeat(' ', 32 - 8 - 11).'ROQUE JANCA'."\n", $p->rowAbajo('Cliente:', 'ROQUE JANCA', 32));

        // No cabe: el primer tramo del nombre va en la MISMA línea que el rótulo y el resto
        // debajo, cada línea alineada a la derecha, sin recortar nada.
        $salida = $p->rowAbajo('Cliente:', 'GESTION ENERGETICA Y OPERACIONES TECNOLOGICAS JM S.A.C', 32);
        $lineas = explode("\n", rtrim($salida, "\n"));
        $this->assertStringStartsWith('Cliente: ', $lineas[0]);
        $this->assertSame('GESTION ENERGETICA Y', trim(substr($lineas[0], 8)), 'el primer tramo va junto al rótulo');
        $this->assertGreaterThanOrEqual(3, count($lineas));
        foreach ($lineas as $l) {
            $this->assertSame(32, mb_strlen($l), 'cada línea llena el ancho');
            $this->assertMatchesRegularExpression('/\S$/', $l, 'alineada a la derecha');
        }
        $texto = trim(preg_replace('/\s+/', ' ', substr($lineas[0], 8).' '.implode(' ', array_slice($lineas, 1))));
        $this->assertSame('GESTION ENERGETICA Y OPERACIONES TECNOLOGICAS JM S.A.C', $texto);
        $this->assertStringNotContainsString('...', $salida);
    }

    public function test_las_vistas_previas_dejan_bajar_el_nombre(): void
    {
        $modal = file_get_contents(resource_path('views/livewire/payments/create.blade.php'));
        $this->assertStringContainsString('<div class="tp-row tp-abajo"><span>Cliente:</span>', $modal);
        $this->assertStringContainsString('.ticket-preview .tp-abajo > span:last-child { flex: 1 1 auto; min-width: 0; white-space: normal; overflow-wrap: anywhere; }', $modal);
        $this->assertStringNotContainsString('.tp-abajo { flex-wrap: wrap; }', $modal, 'el nombre no baja entero: arranca junto al rótulo');

        $recibo = file_get_contents(resource_path('views/payments/ticket.blade.php'));
        $this->assertStringContainsString('<div class="row row-abajo"><span>Cliente:</span>', $recibo);
        $this->assertStringContainsString('.row-abajo > span:last-child { flex: 1 1 auto; min-width: 0; white-space: normal; overflow-wrap: anywhere; }', $recibo);

        $pdf = file_get_contents(resource_path('views/payments/ticket-pdf.blade.php'));
        $this->assertStringContainsString('<tr><td style="white-space:nowrap; padding-right:6pt;">Cliente:</td><td class="der abajo">', $pdf);
        $this->assertStringContainsString('table.fila td.abajo { white-space: normal; word-wrap: break-word; }', $pdf);
    }
}
