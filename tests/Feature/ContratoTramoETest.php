<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo2;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Documentos\ModelosContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tramo E — fidelidad fina contra las maestras + goldens de los 32 modelos.
 *
 *   1. Las viñetas de OCTAVO/NOVENO/DÉCIMO QUINTO/custodia son listas
 *      NUMERADAS (el texto cita "EL NUMERAL PRECEDENTE"); el segundo bloque
 *      de GPS continúa la cuenta tras el párrafo intercalado.
 *   2. "PROPIEDAD: BIENES PROPIO" (sic) solo en los 6 modelos de dos bienes
 *      presentes; los de dos futuros mantienen el singular.
 *   3. El párrafo del cambio de condición (futuro→presente en el SIGM) es
 *      exclusivo del caso mixto (a.x.5) — a.1.3 no lo trae.
 *   4. El título del contrato tiene UNA fuente ($vm->titulo()): carátula y
 *      cita del Anexo 1 en OCTAVO dicen lo mismo, también en las variantes.
 *   5. GOLDEN de los 32 modelos: el texto completo derivado del snapshot se
 *      compara contra fixtures commiteados. Cualquier regresión de partial
 *      aparece como un diff legible.
 */
class ContratoTramoETest extends TestCase
{
    use RefreshDatabase;

    private const DIR_GOLDEN = __DIR__.'/../Fixtures/contratos';

    private Client $client;

    private Credit $credit;

    private Vehiculo $v1;

    private Vehiculo $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede TramoE', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'tramo-e', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9900', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781900', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => 'rosa.e@example.com',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);

        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        $this->v1 = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'TRE111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-E1', 'nro_motor' => 'MOT-E1', 'valor' => 15000,
        ]);
        $this->v2 = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'TRE222', 'marca' => 'NISSAN',
            'modelo' => 'URVAN', 'nro_serie' => 'SER-E2', 'nro_motor' => 'MOT-E2', 'valor' => 12000,
        ]);
    }

    /**
     * $datos DETERMINISTAS para cualquier modelo: fecha fija (el golden no
     * puede depender de now()), sexo del deudor 1 según el preset, y todos
     * los bloques opcionales completos.
     */
    private function datos(string $modelo): array
    {
        $sexo1 = ModelosContrato::get($modelo)['sexo'] ?? 'F';
        $deudor = fn (string $sexo, string $nombre, string $dni) => [
            'sexo' => $sexo, 'nombre' => $nombre, 'dni' => $dni,
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE',
            'estado_civil' => $sexo === 'F' ? 'CASADA' : 'CASADO',
            'domicilio' => 'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'correo' => 'ROSA.E@EXAMPLE.COM',
        ];

        return [
            'fecha' => '2026-08-28',
            'banco' => 'bcp',
            'deudores' => [
                $deudor($sexo1, 'ROSA LINDA QUISPE MAMANI', '46781900'),
                $deudor('M', 'WILDER ROQUE JANCACHAGUA', '10384912'),
            ],
            'empresa' => [
                'razon_social' => 'TRANSPORTES ROSA S.A.C.', 'ruc' => '20601234567',
                'partida' => '15626194', 'oficina_registral' => 'LIMA',
                'correo' => 'CONTACTO@ROSA.COM',
                'domicilio' => 'AV. SALAVERRY 2900, DISTRITO DE MAGDALENA DEL MAR, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'gerente' => [
                    'nombre' => 'JACK BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
                    'nacionalidad' => 'PERUANO', 'ocupacion' => 'EMPRESARIO', 'estado_civil' => 'SOLTERO',
                    'banco' => 'INTERBANK', 'cuenta' => '200-3006962163',
                    'domicilio' => 'AV. SALAVERRY 2900, DISTRITO DE MAGDALENA DEL MAR, PROVINCIA Y DEPARTAMENTO DE LIMA',
                ],
            ],
            'tercero' => [
                'nombre' => 'CARLOS HUAMAN FLORES', 'dni' => '74218017', 'banco' => 'BCP', 'cuenta' => '19172571532076',
                'motivo' => Documentos::MOTIVO_TERCERO,
            ],
            'bienes' => [
                $this->v1->id => ['es_futuro' => true, 'fecha_acta' => '2026-05-04', 'kardex' => '0373-2026', 'notario' => 'JULIO BLAS', 'estado_registral' => 'EN TRÁMITE DE INSCRIPCIÓN'],
                $this->v2->id => ['es_futuro' => false, 'fecha_acta' => '2026-05-04', 'kardex' => '0373-2026', 'notario' => 'JULIO BLAS', 'estado_registral' => 'EN TRÁMITE DE INSCRIPCIÓN'],
            ],
        ];
    }

    /** Texto normalizado del contrato (sin markup ni sangrado). */
    private function texto(string $modelo): string
    {
        $ids = count(ModelosContrato::slots(ModelosContrato::get($modelo)['bienes'])) === 2
            ? [$this->v1->id, $this->v2->id]
            : [$this->v1->id];

        $html = GeneradorContrato::previsualizar($this->client, $this->credit, $ids, $modelo, $this->datos($modelo));

        // Fuera <head> y <style>: strip_tags quita las etiquetas pero no su
        // CONTENIDO, y el CSS no es parte del texto del contrato.
        $html = preg_replace('/<head>.*?<\/head>|<style[^>]*>.*?<\/style>/su', '', (string) $html);

        // Los <li> pierden su numeración al hacer strip_tags: se materializa
        // para que el golden capture la numeración real (y su continuación).
        $html = preg_replace_callback('/<ol class="numerada"(?: start="(\d+)")?>(.*?)<\/ol>/su', function ($m) {
            $n = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 1;

            return preg_replace_callback('/<li>/u', function () use (&$n) {
                return '<li>'.($n++).'. ';
            }, $m[2]);
        }, $html);

        return trim(preg_replace('/\n{3,}/u', "\n\n", (string) preg_replace(
            '/[ \t]+/u', ' ',
            (string) preg_replace('/^[ \t]+|[ \t]+$/mu', '', strip_tags((string) $html))
        )));
    }

    // ── 1 · Listas numeradas ──────────────────────────────────────────────

    public function test_ejecucion_y_gps_van_numeradas_y_gps_continua_tras_el_parrafo(): void
    {
        $texto = $this->texto('a1');

        $this->assertStringContainsString('1. PARA PROCEDER CON LA VENTA EXTRAJUDICIAL', $texto);
        // El segundo bloque de GPS arranca en 5: "EL NUMERAL PRECEDENTE" (el 4) existe.
        $this->assertStringContainsString('5. EN CASO DE LA HIPÓTESIS PREVISTA EN EL NUMERAL PRECEDENTE', $texto);
        $this->assertStringContainsString('NUMERAL PRECEDENTE', $texto);
    }

    public function test_la_constancia_numera_certificacion_e_inserto(): void
    {
        $texto = $this->texto('a1');
        $this->assertStringContainsString('1. AMBAS PARTES CERTIFICAN', $texto);
        $this->assertStringContainsString('2. INSERTO:', $texto);

        // Y en el destino tercero, el INSERTO sigue siendo el numeral 2
        // aunque el párrafo ASIMISMO parta la lista.
        $textoTercero = $this->texto('a11');
        $this->assertStringContainsString('2. INSERTO:', $textoTercero);
    }

    // ── 2 · BIENES PROPIO ─────────────────────────────────────────────────

    public function test_dos_bienes_presentes_dicen_bienes_propio_y_dos_futuros_no(): void
    {
        $dosPresentes = $this->texto('a22');
        $this->assertStringContainsString('PROPIEDAD: BIENES PROPIO', $dosPresentes);

        $dosFuturos = $this->texto('a23');
        $this->assertStringNotContainsString('PROPIEDAD: BIENES PROPIO', $dosFuturos);
        $this->assertStringContainsString('PROPIEDAD: BIEN PROPIO', $dosFuturos);

        $unBien = $this->texto('a2');
        $this->assertStringContainsString('PROPIEDAD: BIEN PROPIO', $unBien);
    }

    // ── 3 · El párrafo exclusivo del mixto ────────────────────────────────

    public function test_el_cambio_de_condicion_solo_aparece_en_el_mixto(): void
    {
        $mixto = $this->texto('a25');
        $this->assertStringContainsString('PASANDO DE BIEN FUTURO A BIEN PRESENTE', $mixto);
        $this->assertStringContainsString('RESPECTO DEL VEHÍCULO 1 CUYA GARANTÍA SE CONSTITUYE EN CALIDAD DE BIEN FUTURO', $mixto);

        // a.x.3 (dos futuros) y a.x.4 (un futuro) NO lo llevan.
        $this->assertStringNotContainsString('PASANDO DE BIEN FUTURO A BIEN PRESENTE', $this->texto('a23'));
        $this->assertStringNotContainsString('PASANDO DE BIEN FUTURO A BIEN PRESENTE', $this->texto('a24'));
    }

    // ── 4 · El título tiene una sola fuente ───────────────────────────────

    public function test_la_caratula_y_la_cita_del_anexo_dicen_el_mismo_titulo(): void
    {
        foreach (['a2' => 'CON CONSTITUCIÓN DE GARANTÍA MOBILIARIA EN EL',
            'a24' => 'CON PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA EN EL',
            'a25' => 'SOBRE BIEN FUTURO Y BIEN PRESENTE EN EL',
            'a26' => 'CON POSESIÓN EN EL'] as $modelo => $fragmento) {
            $texto = $this->texto($modelo);
            $titulo = 'CONTRATO DE CRÉDITO VEHICULAR CON';

            // El título aparece al menos dos veces: carátula + cita en OCTAVO.
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($texto, $fragmento),
                "En {$modelo}, la carátula y la cita del Anexo 1 deben decir el mismo título ({$fragmento})"
            );
        }
    }

    // ── 5 · parsearMonto contra los formatos reales de los vouchers ───────

    /**
     * Los 4 formatos que traen los comprobantes reales del área (además del
     * único que ya funcionaba). El parser anterior devolvía null en tres de
     * ellos y -7000 en el negativo del BBVA, que reventaba el cuadre.
     */
    public function test_parsear_monto_tolera_los_formatos_reales(): void
    {
        $parsear = function (string $v): ?float {
            $m = new \ReflectionMethod(GeneradorAnexo2::class, 'parsearMonto');

            return $m->invoke(null, $v);
        };

        $this->assertSame(8000.00, $parsear('S/ 8,000.00'));
        $this->assertSame(15000.00, $parsear('S/*****15,000.00'), 'BCP enmascara con asteriscos');
        $this->assertSame(12000.00, $parsear('S/ 12.000.00'), 'miles con punto');
        $this->assertSame(25000.00, $parsear('DEPOSITO **** 25,000.00'), 'texto delante del número');
        $this->assertSame(7000.00, $parsear('-7,000.00'), 'el cargo del BBVA en negativo cuadra por magnitud');
        $this->assertSame(1500.00, $parsear('S/. 1.500'), 'separador de miles sin decimales');
        $this->assertSame(950.50, $parsear('S/ 950.50'));
        $this->assertSame(5000.00, $parsear('5000'));
        $this->assertNull($parsear('SIN MONTO'));
        $this->assertNull($parsear(''));
    }

    // ── 6 · Goldens de los 32 modelos ─────────────────────────────────────

    /**
     * El texto derivado de cada modelo se compara contra su fixture. Si un
     * cambio de partial es DELIBERADO, se regeneran con:
     *   REGENERAR_GOLDENS=1 php artisan test --filter=test_golden
     */
    public function test_golden_de_los_32_modelos(): void
    {
        if (! is_dir(self::DIR_GOLDEN)) {
            mkdir(self::DIR_GOLDEN, 0755, true);
        }

        $regenerar = getenv('REGENERAR_GOLDENS') === '1';
        $faltantes = [];

        foreach (array_keys(ModelosContrato::todos()) as $modelo) {
            $texto = $this->texto($modelo);
            $ruta = self::DIR_GOLDEN."/{$modelo}.txt";

            if ($regenerar || ! file_exists($ruta)) {
                file_put_contents($ruta, $texto."\n");
                $faltantes[] = $modelo;

                continue;
            }

            $this->assertSame(
                trim((string) file_get_contents($ruta)),
                $texto,
                "El contrato {$modelo} cambió respecto de su golden (tests/Fixtures/contratos/{$modelo}.txt). ".
                'Si el cambio es deliberado: REGENERAR_GOLDENS=1 php artisan test --filter=test_golden'
            );
        }

        if ($faltantes !== [] && ! $regenerar) {
            $this->markTestIncomplete('Goldens generados por primera vez: '.implode(', ', $faltantes).'. Revisar y commitear.');
        }

        $this->assertTrue(true);
    }
}
