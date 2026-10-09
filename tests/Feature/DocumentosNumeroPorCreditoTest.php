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
 * cuenta CRÉDITOS, no filas. Las filas de un mismo crédito van juntas con la
 * celda N° COMBINADA (rowspan); el zebra alterna por crédito.
 *
 * 10/10/2026 (Antony): "se está listando desde el que se hizo último hasta el
 * que se hizo primero, debe ser al revés, y aumentarle una columna con los
 * números del 1…". Orden cronológico: el crédito más antiguo arriba (N° 1) y,
 * dentro, del documento más viejo al más nuevo; columna # con 1, 2, 3… que se
 * reinicia por préstamo ("por cada préstamo el contador de # se debe reiniciar").
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

    /**
     * Filas en el orden de la tabla: # de la fila, num/rowspan solo en la primera
     * fila de cada crédito (celda combinada), tipo del documento, crédito y si va en gris.
     *
     * @return list<array{fila: string, num: ?string, rowspan: ?string, tipo: string, credito: string, gris: bool}>
     */
    private function filas(string $html): array
    {
        // Livewire 4 envuelve los @if con marcas <!--[if BLOCK]><![endif]-->: se saltan.
        $hueco = '(?:\s|<!--.*?-->)*';   // la marca lleva un ">" dentro: no vale [^>]*
        preg_match_all(
            '/<tr style="([^"]*)">'.$hueco.'(?:<td class="text-center fw-bold align-middle" rowspan="(\d+)">(\d+)<\/td>'.$hueco.')?<td class="text-center text-muted">(\d+)<\/td>'.$hueco.'<td>\s*<span class="badge[^>]*>\s*([^<]+?)\s*<\/span>.*?<td class="text-center">#(\d+)<\/td>/s',
            $html, $m, PREG_SET_ORDER
        );

        return array_map(fn ($f) => [
            'fila' => $f[4],
            'num' => $f[3] !== '' ? $f[3] : null,
            'rowspan' => $f[2] !== '' ? $f[2] : null,
            'tipo' => $f[5],
            'credito' => $f[6],
            'gris' => str_contains($f[1], '#e9ecef'),
        ], $m);
    }

    public function test_los_creditos_van_agrupados_en_orden_cronologico_numerados_y_con_la_celda_combinada(): void
    {
        $a = $this->credito();   // el más viejo → N° 1, arriba
        $b = $this->credito();   // el más reciente → N° 2, abajo
        // Intercalados a propósito: contrato A, anexo1 B, anexo1 A, anexo2 B.
        $this->documento($a, 'contrato');
        $this->documento($b, 'anexo1');
        $this->documento($a, 'anexo1');
        $this->documento($b, 'anexo2');

        $html = Livewire::test(Documentos::class, ['id' => $this->client->id])->html();
        $filas = $this->filas($html);
        $this->assertCount(4, $filas);

        // Arriba el crédito A (el primero que tuvo documento), dentro del más viejo al
        // más nuevo; después el crédito B. Los intercalados quedan juntos.
        $this->assertSame([(string) $a->id, (string) $a->id, (string) $b->id, (string) $b->id], array_column($filas, 'credito'));
        foreach (['Contrato', 'Anexo 1', 'Anexo 1', 'Anexo 2'] as $i => $prefijo) {
            $this->assertStringStartsWith($prefijo, trim(preg_replace('/\s+/', ' ', $filas[$i]['tipo'])), "fila {$i}");
        }

        // # se reinicia por préstamo (1, 2 | 1, 2) y N° ascendente (1 arriba, 2 abajo) en una sola celda combinada por crédito.
        $this->assertSame(['1', '2', '1', '2'], array_column($filas, 'fila'));
        $this->assertSame(['1', null, '2', null], array_column($filas, 'num'));
        $this->assertSame(['2', null, '2', null], array_column($filas, 'rowspan'));

        // Zebra por crédito: el grupo 2 (par) en gris, el 1 en blanco.
        $this->assertSame([false, false, true, true], array_column($filas, 'gris'));
        $this->assertStringNotContainsString('table-striped', $html);
        // N° (por crédito) primero y # (por fila) después.
        $this->assertStringContainsString('<th class="text-center">N°</th>'."\n".'                                <th class="text-center">#</th>', $html);
    }

    public function test_con_un_solo_credito_hay_una_sola_celda_con_el_1_para_todas_sus_filas(): void
    {
        $a = $this->credito();
        $this->documento($a, 'contrato');
        $this->documento($a, 'anexo1');
        $this->documento($a, 'anexo2');

        $filas = $this->filas(Livewire::test(Documentos::class, ['id' => $this->client->id])->html());

        $this->assertCount(3, $filas);
        $this->assertSame(['1', '2', '3'], array_column($filas, 'fila'));
        foreach (['Contrato', 'Anexo 1', 'Anexo 2'] as $i => $prefijo) {
            $this->assertStringStartsWith($prefijo, trim(preg_replace('/\s+/', ' ', $filas[$i]['tipo'])), "fila {$i}: del más viejo al más nuevo");
        }
        $this->assertSame(['1', null, null], array_column($filas, 'num'));
        $this->assertSame(['3', null, null], array_column($filas, 'rowspan'));
        $this->assertSame([false, false, false], array_column($filas, 'gris'));
    }
}
