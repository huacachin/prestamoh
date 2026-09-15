<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Support\Documentos\CorrelativoAnexo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correlativo del Anexo 1 (15/09). El área numera sus anexos "2026-NNN" y esa
 * es la numeración que citan en sus registros y ante la notaría — no el id
 * interno del crédito, que es lo que el anexo mostraba antes.
 *
 * Iban por el 229, así que el primero que emita el sistema es el 2026-230: si
 * arrancara en 1 chocaría con documentos ya firmados.
 */
class CorrelativoAnexoLegalTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede Correlativo', 'status' => 'active']);
        $this->client = Client::create([
            'nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'tipo_documento' => 'DNI',
            'documento' => '46789999', 'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-20',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
    }

    /** Sin anexos emitidos, el primero continúa donde quedó el área. */
    public function test_continua_donde_quedo_el_area(): void
    {
        // La migración sembró el 229 como último usado a mano.
        $this->assertSame(229, (int) DB::table('correlativos')->where('tipo', 'AnexoLegal:2026')->value('correl'));
        $this->assertSame('2026-230', CorrelativoAnexo::proximo(2026));
        $this->assertSame('2026-230', CorrelativoAnexo::siguiente(2026));
    }

    /** proximo() es para la vista previa: mirar no puede quemar un número. */
    public function test_proximo_no_consume(): void
    {
        $antes = CorrelativoAnexo::proximo(2026);

        CorrelativoAnexo::proximo(2026);
        CorrelativoAnexo::proximo(2026);

        $this->assertSame($antes, CorrelativoAnexo::proximo(2026));
    }

    /**
     * El número se toma de los que están EN USO, así que emitir de verdad es
     * lo que lo ocupa (no la mera llamada al asignador).
     */
    public function test_los_emitidos_ocupan_su_numero(): void
    {
        $this->emitido('2026-230');
        $this->assertSame('2026-231', CorrelativoAnexo::proximo(2026));

        $this->emitido('2026-231');
        $this->assertSame('2026-232', CorrelativoAnexo::proximo(2026));
    }

    /**
     * Pedido de Antony (15/09): anular NO debe hacer avanzar la serie. El
     * número del anexo anulado vuelve al ruedo y lo hereda el siguiente.
     */
    public function test_anular_el_ultimo_devuelve_su_numero(): void
    {
        $doc = $this->emitido('2026-230');
        $this->assertSame('2026-231', CorrelativoAnexo::proximo(2026));

        $doc->update(['estado' => 'anulado']);

        $this->assertSame('2026-230', CorrelativoAnexo::proximo(2026));
        $this->assertSame('2026-230', CorrelativoAnexo::siguiente(2026));
    }

    /** Y si el anulado es uno del medio, el hueco se rellena, no se salta. */
    public function test_anular_uno_del_medio_rellena_el_hueco(): void
    {
        $this->emitido('2026-230');
        $delMedio = $this->emitido('2026-231');
        $this->emitido('2026-232');

        $this->assertSame('2026-233', CorrelativoAnexo::proximo(2026));

        $delMedio->update(['estado' => 'anulado']);

        $this->assertSame('2026-231', CorrelativoAnexo::proximo(2026));
    }

    /** Nunca por debajo del piso, aunque no haya ningún anexo emitido. */
    public function test_nunca_baja_del_piso_del_area(): void
    {
        $doc = $this->emitido('2026-230');
        $doc->update(['estado' => 'anulado']);

        $this->assertSame('2026-230', CorrelativoAnexo::proximo(2026));
    }

    /** Los anexos de OTRO año no estorban la serie del año en curso. */
    public function test_la_serie_es_por_anio(): void
    {
        $this->emitido('2026-230');

        // 2027 no tiene piso sembrado: arranca en 1.
        $this->assertSame('2027-1', CorrelativoAnexo::siguiente(2027));

        $this->emitido('2027-1');
        $this->assertSame('2027-2', CorrelativoAnexo::proximo(2027));
        // Y la de 2026 siguió su propia cuenta.
        $this->assertSame('2026-231', CorrelativoAnexo::proximo(2026));
    }

    /** Volver a correr la siembra no debe mover el piso. */
    public function test_la_siembra_es_idempotente(): void
    {
        $this->emitido('2026-230');

        $this->artisan('migrate', ['--force' => true]);

        $this->assertSame(229, (int) DB::table('correlativos')->where('tipo', 'AnexoLegal:2026')->value('correl'));
        $this->assertSame('2026-231', CorrelativoAnexo::proximo(2026));
    }

    /** Anexo 1 emitido con ese correlativo (lo mínimo que mira el asignador). */
    private function emitido(string $correlativo): DocumentoCliente
    {
        return DocumentoCliente::create([
            'client_id' => $this->client->id,
            'credit_id' => $this->credit->id,
            'tipo' => 'anexo1',
            'version' => DocumentoCliente::where('credit_id', $this->credit->id)->max('version') + 1,
            'correlativo' => $correlativo,
            'snapshot' => ['credito' => ['correlativo' => $correlativo]],
            'pdf_path' => 'x.pdf',
            'sha256' => str_repeat('a', 64),
            'estado' => 'emitido',
        ]);
    }
}
