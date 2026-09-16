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
use App\Services\Documentos\RenderDocumento;
use App\Support\Documentos\ModelosContrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El contrato NO puede pasar de 5 hojas (05/09, regla del área legal: las
 * maestras en papel tienen 5). Antes de ajustar los interlineados salían 7 y
 * era invisible desde el código: solo se notaba al imprimir. Este test
 * renderiza el PDF de verdad —con dompdf, la misma ruta que producción— y
 * cuenta los objetos /Type /Page.
 *
 * Si un modelo se pasa, el arreglo NO es subir el tope: es apretar los
 * márgenes en estilos.blade.php (ver .clausula / .parrafo / .clausula-titulo)
 * o revisar qué cláusula creció.
 */
class ContratoPaginasTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_HOJAS = 5;

    private Client $client;

    private Credit $credit;

    private Vehiculo $v1;

    private Vehiculo $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede Paginas', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'paginas', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9901', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781900', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => 'rosa.p@example.com',
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
            'client_id' => $this->client->id, 'placa' => 'PAG111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-P1', 'nro_motor' => 'MOT-P1', 'valor' => 15000,
        ]);
        $this->v2 = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'PAG222', 'marca' => 'NISSAN',
            'modelo' => 'URVAN', 'nro_serie' => 'SER-P2', 'nro_motor' => 'MOT-P2', 'valor' => 12000,
        ]);
    }

    /** Mismos datos deterministas del golden (ContratoTramoETest). */
    private function datos(string $modelo): array
    {
        $sexo1 = ModelosContrato::get($modelo)['sexo'] ?? 'F';
        $deudor = fn (string $sexo, string $nombre, string $dni) => [
            'sexo' => $sexo, 'nombre' => $nombre, 'dni' => $dni,
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE',
            'estado_civil' => $sexo === 'F' ? 'CASADA' : 'CASADO',
            'domicilio' => 'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'correo' => 'ROSA.P@EXAMPLE.COM',
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

    /** Páginas reales del PDF: cuenta los objetos /Type /Page (no /Pages). */
    private function hojas(string $modelo): int
    {
        $ids = count(ModelosContrato::slots(ModelosContrato::get($modelo)['bienes'])) === 2
            ? [$this->v1->id, $this->v2->id]
            : [$this->v1->id];

        // Se arma el snapshot y se renderiza con medio 'pdf' — la MISMA ruta
        // de GeneradorContrato::generar(); previsualizar() usaría 'previa',
        // que no lleva los márgenes de @page y contaría distinto.
        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, $ids, $modelo, $this->datos($modelo)
        );

        $pdf = Pdf::loadHTML(RenderDocumento::html($snapshot, 'contrato', 'pdf'))
            ->setPaper('a4')->output();

        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_ningun_modelo_pasa_de_cinco_hojas(): void
    {
        $excedidos = [];

        foreach (array_keys(ModelosContrato::todos()) as $modelo) {
            $hojas = $this->hojas($modelo);
            if ($hojas > self::MAX_HOJAS) {
                $excedidos[] = "{$modelo}: {$hojas} hojas";
            }
        }

        $this->assertSame([], $excedidos,
            'Contratos que pasan de '.self::MAX_HOJAS.' hojas: '.implode(', ', $excedidos).
            '. Apretar espaciados en estilos.blade.php, no subir el tope.');
    }
}
