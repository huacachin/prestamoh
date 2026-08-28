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
use App\Support\Documentos\Genero;
use App\Support\Documentos\ModelosContrato;
use App\Support\Documentos\Ordinales;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tramo C — el modelo se deduce de la ficha, y el bloque de datos y firmas
 * calca las maestras.
 *
 *   1. El sexo del cliente filtra la serie: un hombre no ve los modelos
 *      "Deudora". Las otras 5 dimensiones dan 22 combinaciones únicas; los
 *      32 modelos son esas 22 más 10 duplicados por género.
 *   2. La persona jurídica tiene DOS ejes de género: el rol DEUDOR va en
 *      masculino y solo la familia CONSTITUYENTE concuerda con la empresa.
 *   3. Dos referencias cruzadas van en ordinal femenino, como en el Word.
 *   4. Dos deudores van en UN párrafo con "AMBOS CON DOMICILIO EN".
 *   5. El rótulo de las firmas es colectivo: a.3 pone "LOS DEUDORES" en las
 *      dos cajas aunque los firmantes sean de distinto sexo.
 */
class ContratoTramoCTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede TramoC', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'tramo-c', 'headquarter_id' => $this->sede->id]));
    }

    // ── 1 · Deducción del modelo ──────────────────────────────────────────

    public function test_los_32_modelos_son_22_contratos_mas_10_pares_de_genero(): void
    {
        $firmas = [];
        foreach (ModelosContrato::MODELOS as $clave => $m) {
            $firmas[json_encode([$m['personas'], $m['gps'], $m['custodia'], $m['bienes'], $m['destino']])][] = $clave;
        }

        $this->assertCount(22, $firmas);

        $duplicados = array_values(array_filter($firmas, fn ($ks) => count($ks) > 1));
        $this->assertCount(10, $duplicados);
        foreach ($duplicados as $par) {
            [$m, $f] = $par;
            $this->assertSame('M', ModelosContrato::MODELOS[$m]['sexo'], "{$m} debe ser el masculino del par");
            $this->assertSame('F', ModelosContrato::MODELOS[$f]['sexo'], "{$f} debe ser el femenino del par");
        }
    }

    public function test_resolver_deduce_la_clave_desde_las_decisiones(): void
    {
        $this->assertSame('a1', ModelosContrato::resolver(1, 'M', 'gps', 'presente', 'propio'));
        $this->assertSame('a2', ModelosContrato::resolver(1, 'F', 'gps', 'presente', 'propio'));
        $this->assertSame('a21', ModelosContrato::resolver(1, 'F', 'gps', 'presente', 'tercero'));
        $this->assertSame('a3', ModelosContrato::resolver(2, null, 'gps', 'presente', 'propio'));
        $this->assertSame('a16', ModelosContrato::resolver(1, 'M', 'custodia', 'presente', 'propio'));
        $this->assertSame('b12', ModelosContrato::resolver(1, 'M', 'sin_gps', '2presentes', 'propio'));
        $this->assertSame('a41', ModelosContrato::resolver('empresa', null, 'gps', 'presente', 'gerente'));
    }

    /**
     * Las combinaciones que el negocio podría pedir pero que NO existen en las
     * maestras. Devolver null es información útil: es lo que debe apagar el
     * botón correspondiente en el wizard.
     */
    public function test_resolver_devuelve_null_para_las_combinaciones_inexistentes(): void
    {
        // No hay depósito a tercero con dos vehículos…
        $this->assertNull(ModelosContrato::resolver(1, 'M', 'gps', '2presentes', 'tercero'));
        // …ni con bien futuro.
        $this->assertNull(ModelosContrato::resolver(1, 'M', 'gps', 'futuro', 'tercero'));
        // No existe bien futuro sin GPS.
        $this->assertNull(ModelosContrato::resolver(1, 'M', 'sin_gps', 'futuro', 'propio'));
        // La custodia solo existe con un bien presente y depósito propio.
        $this->assertNull(ModelosContrato::resolver(1, 'M', 'custodia', '2presentes', 'propio'));
        $this->assertNull(ModelosContrato::resolver(1, 'M', 'custodia', 'presente', 'tercero'));
        // La empresa solo tiene a.4 y a.4.1: nada de custodia, sin GPS ni codeudor.
        $this->assertNull(ModelosContrato::resolver('empresa', null, 'sin_gps', 'presente', 'propio'));
        $this->assertNull(ModelosContrato::resolver('empresa', null, 'gps', 'presente', 'tercero'));
        $this->assertNull(ModelosContrato::resolver('empresa', null, 'gps', 'futuro', 'propio'));
    }

    public function test_aplicables_filtra_por_la_ficha_del_cliente(): void
    {
        $hombre = array_keys(ModelosContrato::aplicables('M'));
        $this->assertContains('a1', $hombre);
        $this->assertNotContains('a2', $hombre, 'un hombre no debe ver los modelos Deudora');
        $this->assertNotContains('a3', $hombre, 'sin codeudor no hay modelos de dos deudores');
        $this->assertNotContains('a4', $hombre, 'una persona natural no ve los de empresa');

        $mujer = array_keys(ModelosContrato::aplicables('F'));
        $this->assertContains('a2', $mujer);
        $this->assertNotContains('a1', $mujer);

        $conCodeudor = array_keys(ModelosContrato::aplicables('M', conCodeudor: true));
        $this->assertContains('a3', $conCodeudor);
        $this->assertNotContains('a1', $conCodeudor);

        $this->assertSame(['a4', 'a41'], array_keys(ModelosContrato::aplicables(null, juridica: true)));
    }

    /**
     * Desde el 28/08 el wizard ya NO tiene selector de 32 modelos: el modelo
     * se RESUELVE desde las decisiones (Guía §5) y el sexo de la ficha. Un
     * hombre con un vehículo presente y depósito propio arranca en a.1 sin
     * elegir nada.
     */
    public function test_el_wizard_resuelve_el_modelo_desde_la_ficha(): void
    {
        [$hombre] = $this->mundo('M');

        $comp = Livewire::test(Documentos::class, ['id' => $hombre->id])
            ->call('abrirModalContrato');

        $this->assertSame('a1', $comp->get('modeloContrato'));
        $this->assertStringContainsString('a.1 GPS. Deudor', $comp->html());
        $this->assertStringNotContainsString('a.2 GPS. Deudora', $comp->html());
    }

    // ── 2 · Los dos ejes de género de la persona jurídica ─────────────────

    public function test_la_empresa_es_deudor_en_masculino_y_constituyente_en_femenino(): void
    {
        $g = Genero::de('F', juridica: true);

        // Eje DEUDOR: masculino (a.4 tiene 44 "EL DEUDOR" y cero "LA DEUDORA").
        $this->assertSame('EL DEUDOR', $g->deudor());
        $this->assertSame('el', $g->el());
        $this->assertSame('el mismo', $g->mismo());
        $this->assertSame('PROHIBIDO', $g->flex('PROHIBIDO'));

        // Eje CONSTITUYENTE: femenino ("SER PROPIETARIA", "ESTAR LEGITIMADA").
        $gc = $g->constituyente();
        $this->assertSame('PROPIETARIA', $gc->propietario());
        $this->assertSame('LEGITIMADA', $gc->flex('LEGITIMADO'));
        $this->assertSame('LA CONSTITUYENTE', $gc->forma('EL CONSTITUYENTE', 'LA CONSTITUYENTE', 'LOS CONSTITUYENTES', 'LAS CONSTITUYENTES'));
    }

    public function test_en_persona_natural_constituyente_no_cambia_nada(): void
    {
        $m = Genero::de('M');
        $this->assertSame('EL DEUDOR', $m->constituyente()->deudor());
        $f = Genero::de('F');
        $this->assertSame('LA DEUDORA', $f->constituyente()->deudor());
        $this->assertSame('PROPIETARIA', $f->constituyente()->propietario());
    }

    // ── 3 · Ordinal femenino ──────────────────────────────────────────────

    public function test_ordinal_femenino(): void
    {
        $this->assertSame('QUINTA', Ordinales::ordinalFemenino(5));
        $this->assertSame('OCTAVA', Ordinales::ordinalFemenino(8));
        $this->assertSame('DÉCIMA PRIMERA', Ordinales::ordinalFemenino(11));
        $this->assertSame('DÉCIMA SÉPTIMA', Ordinales::ordinalFemenino(17));
        // El masculino no se toca
        $this->assertSame('DÉCIMO SÉPTIMO', Ordinales::ordinal(17));
    }

    public function test_las_dos_referencias_cruzadas_van_en_femenino(): void
    {
        [$client, $credit, $v1] = $this->mundo('M');
        $html = GeneradorContrato::previsualizar($client, $credit, [$v1->id], 'a1', $this->datos());

        // OCTAVO cita a QUINTA y NOVENO cita a OCTAVA (verificado en a.1).
        $this->assertStringContainsString('EN LA CLÁUSULA QUINTA', $html);
        $this->assertStringContainsString('EN LA CLAUSULA OCTAVA', $html);
        // …pero los títulos y las citas de SEGUNDO siguen en masculino.
        $this->assertStringContainsString('OCTAVO: FORMAS DE EJECUCIÓN', $html);
        $this->assertStringContainsString('CLÁUSULA TERCERO', $html);
    }

    // ── 4 · Dos deudores en un solo párrafo ───────────────────────────────

    public function test_dos_deudores_van_en_un_parrafo_con_domicilio_compartido(): void
    {
        [$client, $credit, $v1] = $this->mundo('M');
        $dom = 'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA';

        $html = GeneradorContrato::previsualizar($client, $credit, [$v1->id], 'a3', $this->datos() + [
            'deudores' => [
                $this->deudor('JUAN PEREZ', '11111111', 'M', $dom),
                $this->deudor('MARIA LOPEZ', '22222222', 'F', $dom),
            ],
        ]);

        $this->assertStringContainsString('DATOS DE LOS CONSTITUYENTES Y LOS DEUDORES:', $html);
        $this->assertStringContainsString('AMBOS CON DOMICILIO EN', $html);
        // Un solo párrafo: el domicilio aparece UNA vez, no una por deudor.
        $this->assertSame(1, substr_count($html, $dom));
        // Cada deudor conserva su propia flexión.
        $this->assertStringContainsString('JUAN PEREZ, DE NACIONALIDAD PERUANO, IDENTIFICADO', $html);
        $this->assertStringContainsString('MARIA LOPEZ, DE NACIONALIDAD PERUANO, IDENTIFICADA', $html);
    }

    public function test_dos_deudores_con_domicilios_distintos_llevan_el_suyo(): void
    {
        [$client, $credit, $v1] = $this->mundo('M');

        $html = GeneradorContrato::previsualizar($client, $credit, [$v1->id], 'a3', $this->datos() + [
            'deudores' => [
                $this->deudor('JUAN PEREZ', '11111111', 'M', 'CALLE UNO, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'),
                $this->deudor('MARIA LOPEZ', '22222222', 'F', 'CALLE DOS, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA'),
            ],
        ]);

        $this->assertStringNotContainsString('AMBOS CON DOMICILIO EN', $html);
        $this->assertStringContainsString('CALLE UNO', $html);
        $this->assertStringContainsString('CALLE DOS', $html);
    }

    // ── 5 · Rótulo colectivo de las firmas ────────────────────────────────

    public function test_las_firmas_se_rotulan_en_colectivo(): void
    {
        [$client, $credit, $v1] = $this->mundo('M');
        $dom = 'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA';

        $html = GeneradorContrato::previsualizar($client, $credit, [$v1->id], 'a3', $this->datos() + [
            'deudores' => [
                $this->deudor('JUAN PEREZ', '11111111', 'M', $dom),
                $this->deudor('MARIA LOPEZ', '22222222', 'F', $dom),
            ],
        ]);

        // a.3 pone "LOS DEUDORES" en las dos cajas, no EL DEUDOR / LA DEUDORA.
        $this->assertStringContainsString('LOS DEUDORES', $html);
        $this->assertStringNotContainsString('LA DEUDORA', $html);
    }

    // ── 6 · Coherencia modelo ↔ sexo ──────────────────────────────────────

    public function test_el_guard_bloquea_un_modelo_que_no_corresponde_al_sexo(): void
    {
        [$client, $credit, $v1] = $this->mundo('M');

        // a.2 es "Deudora" y el cliente es hombre.
        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v1->id], 'a2', $this->datos()));
        $this->assertStringContainsString('es para un deudor de sexo femenino', $texto);

        // Con el modelo que corresponde, no hay error de género.
        $ok = implode(' | ', GeneradorContrato::validar($client, $credit, [$v1->id], 'a1', $this->datos()));
        $this->assertStringNotContainsString('sexo', $ok);
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private function deudor(string $nombre, string $dni, string $sexo, string $domicilio): array
    {
        return [
            'nombre' => $nombre, 'dni' => $dni, 'sexo' => $sexo,
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE',
            'estado_civil' => $sexo === 'F' ? 'SOLTERA' : 'SOLTERO',
            'domicilio' => $domicilio, 'correo' => strtolower(str_replace(' ', '', $nombre)).'@example.com',
        ];
    }

    private function datos(): array
    {
        return ['banco' => 'bcp'];
    }

    /** @return array{0: Client, 1: Credit, 2: Vehiculo} */
    private function mundo(string $sexo): array
    {
        $client = Client::create([
            'expediente' => '9700', 'nombre' => 'JUAN', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'RUIZ',
            'tipo_documento' => 'DNI', 'documento' => '46781700', 'sexo' => $sexo,
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => 'juan.c@example.com',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE',
            'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        $v = Vehiculo::create([
            'client_id' => $client->id, 'placa' => 'TRC111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-C1', 'nro_motor' => 'MOT-C1', 'valor' => 15000,
        ]);

        return [$client, $credit, $v];
    }
}
