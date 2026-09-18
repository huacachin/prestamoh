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

    /**
     * Transcripción real del maestro "1.1 Anexo 2. BCP. Transf." (datos
     * cambiados). Se deja el "aM" con la mayúscula suelta del original: sirve
     * para comprobar que el render normaliza a mayúsculas.
     */
    private const TRANSCRIPCION = '¡TRANSFERENCIA EXITOSA!; S/5,000.00; SABADO, 22 AGOSTO 2026 – 9:18 aM; '
        .'ENVIADO A BENEFICIARIO DE PRUEBA; ****2097; MONEDA SOLES; DESDE CUENTAS DE AHORRO; '
        .'****5098; NÚMERO DE OPERACIÓN 07365498; MENSAJE: GARANTIA VEHICULAR';

    private function render(array $extra = [], string $medio = 'pdf'): string
    {
        // Reutilizable: varios tests renderizan más de una vez en el mismo caso.
        $sede = Headquarter::firstOrCreate(['name' => 'Sede Anexo2'], ['status' => 'active']);
        $this->actingAs(User::firstWhere('username', 'anexo2-fid')
            ?? User::factory()->create(['username' => 'anexo2-fid', 'headquarter_id' => $sede->id]));

        $client = Client::firstOrCreate(
            ['documento' => '46781234'],
            ['nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
                'tipo_documento' => 'DNI', 'headquarter_id' => $sede->id, 'status' => 'active'],
        );
        $credit = Credit::firstOrCreate(
            ['client_id' => $client->id, 'fecha_prestamo' => '2026-08-20'],
            ['importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
                'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id],
        );

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
        // La transcripción va LITERAL, no traducida a etiquetas nuestras, y en
        // MAYÚSCULAS como el maestro — da igual si la escribió el operador o
        // la lectura automática, el documento sale parejo.
        $this->assertStringContainsString(mb_strtoupper(self::TRANSCRIPCION), $html);
        $this->assertStringNotContainsString('MONTO TRANSFERIDO:', $html);

    }

    public function test_no_tiene_lo_que_el_maestro_no_trae(): void
    {
        $html = $this->render();

        // Membrete y subtítulo de modalidad: fuera.
        $this->assertStringNotContainsString(config('documentos.marca'), $html);
        // Y el pie con las formas de pago (18/09): son las cuentas a las que
        // PAGA el cliente, y esto constata un desembolso ya entregado.
        $this->assertStringNotContainsString(config('documentos.formas_pago'), $html);
        $this->assertStringNotContainsString('TRANSFERENCIA — BCP', $html);
        // Y el pie ya no numera páginas.
        $this->assertStringNotContainsString('Página <span', $html);
    }

    /**
     * El área revisó el documento el 15/09 y pidió fuera el párrafo de tres
     * líneas que identificaba cliente, crédito, banco y fecha: su maestro no
     * lo trae. El vínculo con el crédito queda en la base y en el nombre del
     * archivo, no en la hoja.
     */
    public function test_no_lleva_el_parrafo_de_identificacion(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('LAS PARTES DEJAN CONSTANCIA', $html);
        $this->assertStringNotContainsString('46781234', $html);
        $this->assertStringNotContainsString('EL BANCO DE CRÉDITO DEL PERÚ - BCP', $html);
    }

    /** Título en negrita Y subrayado; subtítulo en negrita (15/09, área legal). */
    public function test_los_titulos_van_como_los_pidio_el_area(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('~\.anexo-titulo\s*\{[^}]*font-weight:\s*bold~s', $html);
        $this->assertMatchesRegularExpression('~\.anexo-titulo\s*\{[^}]*text-decoration:\s*underline~s', $html);
        $this->assertMatchesRegularExpression('~\.anexo-subtitulo\s*\{[^}]*font-weight:\s*bold~s', $html);
    }

    /** "DETALLES:" lo pone la plantilla: venga o no en la transcripción. */
    public function test_detalles_no_se_duplica(): void
    {
        foreach (['DETALLES: '.self::TRANSCRIPCION, self::TRANSCRIPCION] as $entrada) {
            $html = $this->render(['transcripcion' => $entrada]);
            $this->assertSame(1, mb_substr_count($html, 'DETALLES:'), "Se duplicó con: {$entrada}");
        }
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
