<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Documentos\GeneradorAnexo2;
use App\Services\Documentos\RenderDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Anexo 2 debe salir como el maestro que el área legal firma (15/09,
 * calcado de las 15 plantillas .docx de ~/Desktop/anexo2). El maestro tiene
 * CUATRO cosas y nada más:
 *
 *   ANEXO 2
 *   CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL
 *   [imagen del voucher]
 *   DETALLES: <transcripción literal del voucher>.
 *   (al pie) Formas de pago: ...
 *
 * Lo ÚNICO que añadimos a propósito es el párrafo que identifica cliente,
 * crédito, banco y fecha: el maestro va grapado al contrato, el nuestro se
 * descarga suelto y sin esa línea no diría a qué préstamo pertenece.
 *
 * Antes de esto el sistema generaba además un membrete, un subtítulo con la
 * modalidad, la imagen al final y la línea de detalles con etiquetas propias
 * ("MONTO TRANSFERIDO: ..."), que no es lo que el área firma.
 */
class AnexoDosFidelidadTest extends TestCase
{
    use RefreshDatabase;

    /** Transcripción real del maestro "1.1 Anexo 2. BCP. Transf." (datos cambiados). */
    private const TRANSCRIPCION = '¡TRANSFERENCIA EXITOSA!; S/5,000.00; SABADO, 22 AGOSTO 2026 – 9:18 aM; '
        .'ENVIADO A BENEFICIARIO DE PRUEBA; ****2097; MONEDA SOLES; DESDE CUENTAS DE AHORRO; '
        .'****5098; NÚMERO DE OPERACIÓN 07365498; MENSAJE: GARANTIA VEHICULAR';

    private function render(array $extra = [], string $medio = 'pdf'): string
    {
        $sede = Headquarter::create(['name' => 'Sede Anexo2', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'anexo2-fid', 'headquarter_id' => $sede->id]));

        $client = Client::create([
            'nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781234',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-20',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);

        $snapshot = GeneradorAnexo2::construirSnapshot($client, $credit, array_merge([
            'banco' => 'bcp', 'modalidad' => 'transferencia',
            'monto' => '5,000.00', 'transcripcion' => self::TRANSCRIPCION,
            'fecha' => '22/08/2026', 'imagen_path' => null,
        ], $extra));

        return RenderDocumento::html(array_merge($snapshot, $extra['_snapshot'] ?? []), 'anexo2', $medio);
    }

    public function test_tiene_lo_del_maestro(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('ANEXO 2', $html);
        $this->assertStringContainsString('CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL', $html);
        // La transcripción va LITERAL, no traducida a etiquetas nuestras.
        $this->assertStringContainsString(self::TRANSCRIPCION, $html);
        $this->assertStringNotContainsString('MONTO TRANSFERIDO:', $html);
        // Pie del maestro: las formas de pago.
        $this->assertStringContainsString(config('documentos.formas_pago'), $html);
    }

    public function test_no_tiene_lo_que_el_maestro_no_trae(): void
    {
        $html = $this->render();

        // Membrete y subtítulo de modalidad: fuera.
        $this->assertStringNotContainsString(config('documentos.marca'), $html);
        $this->assertStringNotContainsString('TRANSFERENCIA — BCP', $html);
        // Y el pie ya no numera páginas.
        $this->assertStringNotContainsString('Página <span', $html);
    }

    public function test_conserva_la_identificacion_del_credito(): void
    {
        $html = $this->render();

        // Lo único que añadimos al maestro, a propósito.
        $this->assertStringContainsString('QUISPE', $html);
        $this->assertStringContainsString('46781234', $html);
        $this->assertStringContainsString('EL BANCO DE CRÉDITO DEL PERÚ - BCP', $html);
        $this->assertStringContainsString('22/08/2026', $html);
    }

    public function test_la_imagen_va_arriba_de_los_detalles(): void
    {
        // En la previa, sin imagen subida, se pinta el recuadro placeholder:
        // sirve igual para fijar el ORDEN, que es lo que se rompe al editar.
        $html = $this->render(medio: 'previa');

        $posImagen = mb_strpos($html, 'voucher-img');
        $posDetalles = mb_strpos($html, 'DETALLES:');
        $this->assertNotFalse($posImagen);
        $this->assertNotFalse($posDetalles);
        $this->assertLessThan($posDetalles, $posImagen, 'La imagen del voucher va ARRIBA de los detalles, como en el maestro.');
    }

    /** Un documento ya emitido no puede cambiar de aspecto al tocar la vista. */
    public function test_los_snapshots_viejos_se_siguen_viendo(): void
    {
        $html = $this->render(extra: ['_snapshot' => [
            'transcripcion' => [
                ['label' => 'MONTO TRANSFERIDO', 'valor' => '5,000.00'],
                ['label' => 'N° DE OPERACIÓN', 'valor' => '00112233'],
            ],
        ]]);

        $this->assertStringContainsString('MONTO TRANSFERIDO: 5,000.00; N° DE OPERACIÓN: 00112233.', $html);
    }
}
