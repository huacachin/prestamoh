<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026: la tabla de documentos del cliente lleva una columna N° que
 * cuenta CRÉDITOS, no filas: contrato, anexo 1 y anexo 2 de un mismo crédito
 * comparten número (por orden de aparición, porque la lista va por id
 * descendente y los documentos de un crédito pueden intercalarse) y el zebra
 * alterna por crédito.
 */
class DocumentosNumeroPorCreditoTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede Num', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'num-tester', 'headquarter_id' => $sede->id]));
        $this->client = Client::create([
            'expediente' => '9700', 'nombre' => 'ANA', 'apellido_pat' => 'NUMERA', 'apellido_mat' => 'DOCS',
            'tipo_documento' => 'DNI', 'documento' => '47300001', 'sexo' => 'F',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
    }

    private function credito(): Credit
    {
        return Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-01',
            'importe' => 5000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->client->headquarter_id,
        ]);
    }

    private function documento(Credit $credit, string $tipo, int $version = 1): DocumentoCliente
    {
        return DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $credit->id, 'tipo' => $tipo, 'version' => $version,
            'snapshot' => ['prueba' => true], 'pdf_path' => "documentos/cliente-{$this->client->id}/{$tipo}-credito-{$credit->id}-v{$version}.pdf",
            'sha256' => hash('sha256', "{$credit->id}{$tipo}{$version}"), 'estado' => 'emitido', 'generado_por' => auth()->id(),
        ]);
    }

    /** @return list<array{num: string, credito: string, clase: string}> filas en el orden de la tabla */
    private function filas(string $html): array
    {
        preg_match_all(
            '/<tr class="([a-z-]*)" style="[^"]*">\s*<td class="text-center fw-bold">(\d+)<\/td>.*?<td class="text-center">#(\d+)<\/td>/s',
            $html, $m, PREG_SET_ORDER
        );

        return array_map(fn ($f) => ['num' => $f[2], 'credito' => $f[3], 'clase' => $f[1]], $m);
    }

    public function test_los_documentos_de_un_mismo_credito_comparten_numero_y_el_zebra_va_por_credito(): void
    {
        $a = $this->credito();
        $b = $this->credito();
        // Intercalados a propósito: contrato A, anexo1 B, anexo1 A, anexo2 B.
        $this->documento($a, 'contrato');
        $this->documento($b, 'anexo1');
        $this->documento($a, 'anexo1');
        $this->documento($b, 'anexo2');

        $html = Livewire::test(Documentos::class, ['id' => $this->client->id])->html();
        $filas = $this->filas($html);

        // La lista va por id descendente: el más nuevo (anexo2 de B) primero.
        $this->assertSame(['1', '2', '1', '2'], array_column($filas, 'num'));
        $this->assertSame([(string) $b->id, (string) $a->id, (string) $b->id, (string) $a->id], array_column($filas, 'credito'));

        // Zebra por crédito: el grupo 2 lleva table-light, el 1 no.
        $this->assertSame(['', 'table-light', '', 'table-light'], array_column($filas, 'clase'));
        $this->assertStringNotContainsString('table-striped', $html);
    }

    public function test_con_un_solo_credito_todas_las_filas_son_el_numero_1(): void
    {
        $a = $this->credito();
        $this->documento($a, 'contrato');
        $this->documento($a, 'anexo1');
        $this->documento($a, 'anexo2');

        $filas = $this->filas(Livewire::test(Documentos::class, ['id' => $this->client->id])->html());

        $this->assertCount(3, $filas);
        $this->assertSame(['1', '1', '1'], array_column($filas, 'num'));
    }
}
