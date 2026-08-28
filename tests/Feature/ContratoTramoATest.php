<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Documentos\ModelosContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tramo A — correcciones de fidelidad contra las 32 maestras Word del área
 * legal (~/projects/legal/.../1. Modelos. Contratos SIGM/).
 *
 * Cubre los seis defectos que hacían que el contrato emitido dijera algo
 * distinto del modelo firmado por el área:
 *   1. slotsFuturoDe() no entendía 'futuro' ni 'futuro_presente' → los 6
 *      modelos .4/.5 se emitían como el contrato base.
 *   2. El wizard mandaba 'acta' y el generador leía 'acta_notarial' → los
 *      datos del acta se perdían; 'fecha_acta' no se mandaba nunca.
 *   3. El gerente viajaba como 'genero' y se leía como 'sexo' → toda gerenta
 *      salía IDENTIFICADO/SOLTERO.
 *   4. "EN EL {{ $banco }}" con un nombre legal que ya trae el artículo →
 *      "EN EL EL BANCO DE CRÉDITO DEL PERÚ".
 *   5. Faltaba la viñeta "INSERTO:", que es la que remite al Anexo 2.
 *   6. La viñeta del art. 47.8 (5 días para entregar la posesión) se emitía
 *      también en custodia, donde el bien YA fue entregado.
 */
class ContratoTramoATest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $v1;

    private Vehiculo $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede TramoA', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'tramo-a', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9101',
            'nombre' => 'ROSA LINDA',
            'apellido_pat' => 'QUISPE',
            'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI',
            'documento' => '46781299',
            'sexo' => 'F',
            'direccion' => 'MZ. B LOTE 12 AAHH LOS JARDINES',
            'distrito' => 'ATE',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
            'celular1' => '987654321',
            'email' => 'rosa.tramoa@example.com',
            'headquarter_id' => $sede->id,
            'status' => 'active',
        ]);

        $this->credit = Credit::create([
            'client_id' => $this->client->id,
            'fecha_prestamo' => '2026-08-25',
            'importe' => 5000,
            'cuotas' => 4,
            'tipo_planilla' => 1,
            'interes' => 10,
            'situacion' => 'Activo',
            'estado' => 1,
            'headquarter_id' => $sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id,
                'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250,
                'importe_interes' => 50,
                'importe_excedente' => 0,
                'importe_aplicado' => 0,
                'interes_aplicado' => 0,
                'excedente_aplicado' => 0,
                'importe_mora' => 0,
                'mora_interes' => 0,
                'pagado' => false,
            ]);
        }

        $this->v1 = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'TRA111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-111', 'nro_motor' => 'MOT-111', 'valor' => 15000,
        ]);
        $this->v2 = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'TRA222', 'marca' => 'NISSAN',
            'modelo' => 'URVAN', 'nro_serie' => 'SER-222', 'nro_motor' => 'MOT-222', 'valor' => 12000,
        ]);
    }

    /** Deudor prellenado como lo dejaría el wizard. */
    private function deudor(string $sexo = 'F'): array
    {
        return [
            'sexo' => $sexo,
            'nombre' => mb_strtoupper($this->client->fullName()),
            'dni' => $this->client->documento,
            'nacionalidad' => 'PERUANO',
            'ocupacion' => 'COMERCIANTE',
            'estado_civil' => 'SOLTERA',
            'domicilio' => 'MZ. B LOTE 12, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'correo' => 'ROSA.TRAMOA@EXAMPLE.COM',
        ];
    }

    /**
     * $datos completos para CUALQUIER modelo: dos deudores, empresa+gerenta,
     * tercero y los datos de acta de ambos vehículos. Cada preset toma solo
     * lo que le corresponde, así que sirve para recorrer los 32.
     */
    private function datosCompletos(?string $modelo = null): array
    {
        // El modelo y el sexo del deudor deben concordar (los 10 pares
        // Deudor/Deudora solo se diferencian en eso), así que el primer
        // deudor toma el sexo que el preset declara.
        $sexo = $modelo !== null ? (ModelosContrato::get($modelo)['sexo'] ?? 'F') : 'F';

        return [
            'deudores' => [$this->deudor($sexo ?? 'F'), $this->deudor('M')],
            'empresa' => [
                'razon_social' => 'TRANSPORTES ROSA S.A.C.',
                'ruc' => '20601234567',
                'partida' => '15626194',
                'oficina_registral' => 'LIMA',
                'correo' => 'CONTACTO@ROSA.COM',
                'domicilio' => 'AV. SALAVERRY 2900, DISTRITO DE MAGDALENA DEL MAR, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'gerente' => [
                    'nombre' => 'ROSA LINDA QUISPE MAMANI',
                    'dni' => '46781299',
                    'sexo' => 'F',
                    'nacionalidad' => 'PERUANO',
                    'ocupacion' => 'EMPRESARIA',
                    'estado_civil' => 'CASADA',
                    'domicilio' => 'AV. SALAVERRY 2900, DISTRITO DE MAGDALENA DEL MAR, PROVINCIA Y DEPARTAMENTO DE LIMA',
                ],
            ],
            'tercero' => [
                'nombre' => 'CARLOS HUAMAN FLORES',
                'dni' => '74218017',
                'cuenta' => '19172571532076',
                'motivo' => Documentos::MOTIVO_TERCERO,
            ],
            'banco' => 'bcp',
            'bienes' => [
                $this->v1->id => ['es_futuro' => true, 'fecha_acta' => '2026-05-04', 'kardex' => '0373-2026', 'notario' => 'JULIO WILDER BLAS ALIPAZAGA'],
                $this->v2->id => ['es_futuro' => false, 'fecha_acta' => '2026-05-04', 'kardex' => '0373-2026', 'notario' => 'JULIO WILDER BLAS ALIPAZAGA'],
            ],
        ];
    }

    private function html(string $modelo): string
    {
        $ids = count(ModelosContrato::slots(ModelosContrato::get($modelo)['bienes'])) === 2
            ? [$this->v1->id, $this->v2->id]
            : [$this->v1->id];

        return GeneradorContrato::previsualizar($this->client, $this->credit, $ids, $modelo, $this->datosCompletos($modelo));
    }

    // ── 1 · El mapa de slots ──────────────────────────────────────────────

    public function test_slots_por_tipo_de_bien(): void
    {
        $this->assertSame([false], ModelosContrato::slots('presente'));
        $this->assertSame([true], ModelosContrato::slots('futuro'));
        $this->assertSame([false, false], ModelosContrato::slots('2presentes'));
        $this->assertSame([true, true], ModelosContrato::slots('2futuros'));
        // El ORDEN importa: en a.1.5 el vehículo 1 es el futuro.
        $this->assertSame([true, false], ModelosContrato::slots('futuro_presente'));
        $this->assertSame([false], ModelosContrato::slots('cualquier-cosa'));
    }

    public function test_los_seis_modelos_de_bien_futuro_ya_no_caen_a_bien_presente(): void
    {
        foreach (['a14', 'a24', 'a34'] as $clave) {
            $slots = ModelosContrato::slots(ModelosContrato::get($clave)['bienes']);
            $this->assertSame([true], $slots, "{$clave} debe pedir UN bien futuro");
        }

        foreach (['a15', 'a25', 'a35'] as $clave) {
            $slots = ModelosContrato::slots(ModelosContrato::get($clave)['bienes']);
            $this->assertSame([true, false], $slots, "{$clave} debe pedir DOS bienes: futuro y presente");
        }
    }

    public function test_los_32_modelos_declaran_un_tipo_de_bien_conocido(): void
    {
        foreach (ModelosContrato::todos() as $clave => $preset) {
            $this->assertArrayHasKey(
                $preset['bienes'],
                ModelosContrato::SLOTS_BIENES,
                "El modelo {$clave} declara bienes='{$preset['bienes']}', que no está en el mapa"
            );
        }
    }

    // ── 2 · Los datos del acta llegan al documento ────────────────────────

    public function test_el_acta_de_transferencia_llega_al_contrato(): void
    {
        $html = $this->html('a14');

        // Formato de las maestras: "04 DE MAYO DEL 2026".
        $this->assertStringContainsString('SE HA REALIZADO EL DÍA 04 DE MAYO DEL 2026', $html);
        $this->assertStringContainsString('KARDEX N° 0373-2026', $html);
        $this->assertStringContainsString('NOTARIO PÚBLICO JULIO WILDER BLAS ALIPAZAGA', $html);
    }

    public function test_no_se_imprime_numero_de_acta_porque_ninguna_maestra_lo_cita(): void
    {
        $html = $this->html('a13');

        $this->assertStringContainsString('MEDIANTE ACTA DE TRANSFERENCIA VEHICULAR Y CON KARDEX', $html);
        $this->assertStringNotContainsString('ACTA DE TRANSFERENCIA VEHICULAR N°', $html);
    }

    // ── 3 · Género del gerente ────────────────────────────────────────────

    public function test_gerenta_se_flexiona_en_femenino(): void
    {
        $html = $this->html('a4');

        $this->assertStringContainsString('IDENTIFICADA', $html);
        $this->assertStringNotContainsString('IDENTIFICADO CON DNI N° 46781299', $html);
    }

    // ── 4 · El artículo del banco ─────────────────────────────────────────

    public function test_el_banco_no_duplica_el_articulo(): void
    {
        foreach (['a1', 'a11', 'a41'] as $modelo) {
            $html = $this->html($modelo);
            $this->assertStringNotContainsString('EN EL EL BANCO', $html, "modelo {$modelo}");
            $this->assertStringContainsString('EN EL BANCO DE CRÉDITO DEL PERÚ - BCP', $html, "modelo {$modelo}");
        }
    }

    // ── 5 · La viñeta INSERTO ─────────────────────────────────────────────

    public function test_la_vineta_inserto_remite_al_anexo_2_en_los_tres_destinos(): void
    {
        foreach (['a1' => 'propio', 'a11' => 'tercero', 'a41' => 'gerente'] as $modelo => $destino) {
            $html = $this->html($modelo);
            $this->assertStringContainsString('INSERTO:', $html, "destino {$destino}");
            $this->assertStringContainsString(
                'CUYO TENOR GRÁFICO Y LITERAL SE ENCUENTRA EN EL ANEXO 2 DEL PRESENTE CONTRATO.',
                $html,
                "destino {$destino}"
            );
        }
    }

    // ── 6 · La contradicción de custodia ──────────────────────────────────

    public function test_custodia_no_exige_entregar_lo_que_ya_fue_entregado(): void
    {
        $conGps = $this->html('a1');
        $this->assertStringContainsString('ARTÍCULO 47.8', $conGps, 'a.1 sí lleva la viñeta del 47.8');

        foreach (['a16', 'a26', 'a36'] as $clave) {
            $html = $this->html($clave);
            $this->assertStringNotContainsString('ARTÍCULO 47.8', $html, "{$clave} no debe exigir la entrega en 5 días");
            $this->assertStringNotContainsString('CINCO (05) DÍAS HÁBILES SIGUIENTES A LA NOTIFICACIÓN', $html, $clave);
        }
    }

    // ── 7 · Test negativo de huecos ───────────────────────────────────────

    /**
     * Barre los 32 modelos con TODOS los datos completos y verifica que no
     * quede ningún hueco de plantilla. Es la red que habría cazado el acta
     * perdida y el doble artículo antes de que llegaran al PDF.
     */
    public function test_ningun_modelo_emite_huecos_ni_articulos_duplicados(): void
    {
        $prohibidos = [
            ', ,' => 'campo vacío entre comas',
            'N° ,' => 'número vacío',
            'N°,' => 'número vacío',
            'DEBIDO A QUE ,' => 'motivo del tercero vacío',
            'EL DÍA ,' => 'fecha de acta vacía',
            'EN EL EL' => 'artículo duplicado',
            'EN EL LA' => 'artículo duplicado',
            'PÚBLICO .' => 'notario vacío',
            '  ' => 'doble espacio por variable vacía',
        ];

        foreach (array_keys(ModelosContrato::todos()) as $modelo) {
            $html = $this->html($modelo);
            // El HTML viene indentado por Blade: solo interesan los huecos
            // dentro del texto de las cláusulas, no el sangrado del markup.
            $texto = preg_replace('/\s+/u', ' ', strip_tags($html));

            foreach ($prohibidos as $patron => $motivo) {
                $this->assertStringNotContainsString(
                    $patron,
                    $texto,
                    "El modelo {$modelo} emite un hueco ({$motivo}): '{$patron}'"
                );
            }
        }
    }
}
