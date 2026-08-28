<?php

namespace Tests\Feature\Legal;

use App\Models\Client;
use App\Models\Contrato;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Legal\ContratoViewModelFactory;
use App\Services\Legal\GeneradorContrato;
use App\Support\Legal\Ordinales;
use Carbon\Carbon;
use Database\Seeders\LegalSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matriz C1-C8 del motor documental del contrato de garantía mobiliaria:
 * previsualizar() debe producir el documento correcto para cada combinación
 * (género/número de deudores, persona jurídica, GPS, custodia, bien futuro,
 * destino del desembolso) y generar() debe emitir el PDF versionado con
 * numeración correlativa, snapshot y hash. Los asserts van contra el CONTRATO
 * DE LA API (títulos de cláusula via Ordinales, flexiones de Genero, montos
 * de $vm->monto()) — las posiciones esperadas se CALCULAN con
 * ContratoViewModelFactory::clausulas(), nunca se hardcodean a ciegas.
 */
class GeneradorContratoTest extends TestCase
{
    use RefreshDatabase;

    /** Fecha fija del contrato → fechaSimple determinista "09 DE MARZO DEL 2026" */
    private const FECHA = '2026-03-09';

    private const FECHA_SIMPLE = '09 DE MARZO DEL 2026';

    /** PNG 1x1 válido para la imagen del voucher (dompdf lo inserta de verdad) */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $user;

    // ─────────────────────────────── Mundo mínimo ───────────────────────────────

    /**
     * Arma el mundo mínimo del contrato: crédito de S/ 7,000 en 28 cuotas
     * semanales de S/ 320.00 (250 + 70 + 0) → cronograma real S/ 8,960.00 que
     * CUADRA con el monto_gravamen. Opciones: sexo, tipo_persona, gps,
     * custodia, codeudor_sexo, bien ('presente'|'futuro'), segundo_bien.
     */
    private function mundo(array $opts = []): Garantia
    {
        $opts += [
            'sexo' => 'M',
            'tipo_persona' => 'natural',
            'gps' => true,
            'custodia' => false,
            'codeudor_sexo' => null,
            'bien' => 'presente',
            'segundo_bien' => null,
        ];

        $this->user = User::factory()->create(['username' => 'legal-f2-'.uniqid()]);
        $sede = Headquarter::create(['name' => 'Sede Legal']);

        $client = $this->cliente($opts['sexo'], '45678912', 'PEREZ', 'RAMOS', $opts['sexo'] === 'F' ? 'JUANA' : 'JUAN');

        $codeudor = null;
        if ($opts['codeudor_sexo']) {
            $codeudor = $this->cliente(
                $opts['codeudor_sexo'], '41239876', 'QUISPE', 'MAMANI',
                $opts['codeudor_sexo'] === 'F' ? 'MARIA' : 'MARIO'
            );
        }

        $credit = Credit::create([
            'client_id' => $client->id,
            'fecha_prestamo' => self::FECHA,
            'importe' => 7000,
            'cuotas' => 28,
            'tipo_planilla' => 1, // semanal
            'user_id' => $this->user->id,
            'headquarter_id' => $sede->id,
        ]);

        foreach (range(1, 28) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id,
                'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse(self::FECHA)->addWeeks($n)->toDateString(),
                'importe_cuota' => 250,
                'importe_interes' => 70,
                'importe_excedente' => 0,
            ]);
        }

        $garantia = Garantia::create([
            'credit_id' => $credit->id,
            'client_id' => $client->id,
            'codeudor_client_id' => $codeudor?->id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => $opts['tipo_persona'],
            'gps' => $opts['gps'],
            'custodia' => $opts['custodia'],
            'monto_gravamen' => 8960, // = 28 × 320.00 del cronograma real
            'registrado_por' => $this->user->id,
        ]);

        $this->adjuntarBien($garantia, $client, 'ABC-123', $opts['bien'], 1);
        if ($opts['segundo_bien']) {
            $this->adjuntarBien($garantia, $client, 'XYZ-789', $opts['segundo_bien'], 2);
        }

        (new LegalSettingsSeeder)->run();

        return $garantia;
    }

    private function cliente(string $sexo, string $dni, string $apPat, string $apMat, string $nombre): Client
    {
        return Client::create([
            'nombre' => $nombre,
            'apellido_pat' => $apPat,
            'apellido_mat' => $apMat,
            'documento' => $dni,
            'sexo' => $sexo,
            'estado_civil' => $sexo === 'F' ? 'SOLTERA' : 'SOLTERO',
            'ocupacion' => 'COMERCIANTE',
            'direccion' => 'AV. LOS INCAS 123',
            'distrito' => 'ATE',
            'email' => mb_strtolower($nombre).'@correo.test',
        ]);
    }

    private function adjuntarBien(Garantia $garantia, Client $client, string $placa, string $tipo, int $orden): void
    {
        $vehiculo = Vehiculo::create([
            'client_id' => $client->id,
            'placa' => $placa,
            'marca' => 'TOYOTA',
            'modelo' => 'HILUX',
            'nro_motor' => "MTR-{$orden}54321",
            'nro_serie' => "SER-{$orden}98765",
            'categoria' => 'N1',
            'anio_modelo' => '2019',
            'carroceria' => 'PICK UP',
            'color' => 'BLANCO',
            'combustible' => 'DIESEL',
            'valor' => 9000,
        ]);

        $pivot = ['orden' => $orden, 'es_bien_futuro' => false];
        if ($tipo === 'futuro') {
            $pivot = [
                'orden' => $orden,
                'es_bien_futuro' => true,
                'acta_notarial' => 'ACTA DE TRANSFERENCIA VEHICULAR',
                'kardex' => 'KARDEX-0373-2026',
                'notario' => 'NOTARIO RICARDO FERNANDINI',
                'fecha_acta' => self::FECHA,
            ];
        }

        $garantia->vehiculos()->attach($vehiculo->id, $pivot);
    }

    private function parametros(array $extra = []): array
    {
        return array_merge(['fecha' => self::FECHA], $extra);
    }

    private function empresa(string $generoGerente = 'M', string $nombreGerente = 'PEDRO PABLO SALAS ROJAS'): array
    {
        return [
            'razon_social' => 'TRANSPORTES ANDINOS S.A.C.',
            'ruc' => '20601234567',
            'partida' => '13579246',
            'oficina_registral' => 'LIMA',
            'domicilio' => 'AV. INDUSTRIAL 456, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'correo' => 'GERENCIA@ANDINOS.TEST',
            'gerente' => [
                'genero' => $generoGerente,
                'nombre' => $nombreGerente,
                'dni' => '40111222',
                'ocupacion' => $generoGerente === 'F' ? 'EMPRESARIA' : 'EMPRESARIO',
                'estado_civil' => $generoGerente === 'F' ? 'CASADA' : 'CASADO',
                'domicilio' => 'AV. PRIMAVERA 789, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            ],
        ];
    }

    private function tercero(): array
    {
        return [
            'nombre' => 'CARLOS ALBERTO RIOS VEGA',
            'dni' => '43219876',
            'cuenta' => '191-98765432-0-55',
            'motivo' => 'NO CUENTA CON CUENTA BANCARIA ACTIVA A SU NOMBRE',
        ];
    }

    private function voucherCompleto(): array
    {
        return [
            'banco' => 'bcp',
            'modalidad' => 'transferencia',
            'campos' => [
                'monto' => '7,000.00',
                'fecha_hora' => '09/03/2026 10:15 AM',
                'beneficiario' => 'CARLOS ALBERTO RIOS VEGA',
                'cuenta_destino' => '191-98765432-0-55',
                'nro_operacion' => '00123456',
            ],
            'imagen_path' => 'legal/vouchers/voucher.png',
        ];
    }

    private function previsualizar(Garantia $garantia, array $parametros): string
    {
        return (new GeneradorContrato)->previsualizar($garantia, $parametros);
    }

    // ─────────────────────────────── Matriz C1-C8 ───────────────────────────────

    /** C1: deudor masculino, GPS, 1 bien presente, desembolso a cuenta propia */
    public function test_c1_deudor_masculino_con_gps_bien_presente_destino_propio(): void
    {
        $garantia = $this->mundo();

        $html = $this->previsualizar($garantia, $this->parametros());

        // La cláusula GPS ocupa la posición 9 en este contrato (calculado, no asumido)
        $ord = new Ordinales(ContratoViewModelFactory::clausulas(gps: true, custodia: false));
        $this->assertSame('NOVENO', $ord->de('gps'));

        $this->assertStringContainsString('DISPOSITIVO GPS', $html);
        $posOrdinal = strpos($html, 'NOVENO: ');
        $this->assertNotFalse($posOrdinal, "El título 'NOVENO: ' debe existir en el HTML");
        $this->assertNotFalse(
            strpos($html, 'DISPOSITIVO GPS', $posOrdinal),
            "El ordinal 'NOVENO: ' debe titular la cláusula GPS (aparecer antes de DISPOSITIVO GPS)"
        );

        $this->assertStringContainsString('EL DEUDOR', $html);
        $this->assertStringContainsString('S/ 8,960.00 (OCHO MIL NOVECIENTOS SESENTA CON 00/100 SOLES)', $html);
        $this->assertStringContainsString('28 CUOTAS', $html);

        // La fecha se consigna al menos dos veces (cuerpo del contrato y firma)
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, self::FECHA_SIMPLE),
            'La fecha simple debe aparecer al menos 2 veces en el documento'
        );
    }

    /** C2: deudora femenina sin GPS → flexión F y renumeración de representantes */
    public function test_c2_deudora_sin_gps_flexiona_y_renumera_representantes(): void
    {
        $garantia = $this->mundo(['sexo' => 'F', 'gps' => false]);

        $html = $this->previsualizar($garantia, $this->parametros());

        $this->assertStringContainsString('LA DEUDORA', $html);
        $this->assertStringContainsString('IDENTIFICADA', $html);
        $this->assertStringNotContainsString('DISPOSITIVO GPS', $html);

        // Sin GPS ni custodia, representantes sube a la posición 9
        $ord = new Ordinales(ContratoViewModelFactory::clausulas(gps: false, custodia: false));
        $this->assertSame('NOVENO', $ord->de('representantes'));
        $this->assertStringContainsString('NOVENO: REPRESENTANTES', $html);
    }

    /** C3: dos deudores M+F con GPS → plural masculino, dos bloques de datos y de firma */
    public function test_c3_dos_deudores_mixtos_con_gps(): void
    {
        $garantia = $this->mundo(['sexo' => 'M', 'codeudor_sexo' => 'F']);

        $html = $this->previsualizar($garantia, $this->parametros());

        $this->assertStringContainsString('LOS DEUDORES', $html);

        // Cada deudor aparece al menos dos veces: en su bloque de datos y en su firma
        $this->assertGreaterThanOrEqual(
            2, substr_count($html, 'PEREZ RAMOS JUAN'),
            'El primer deudor debe tener bloque de datos y juego de firma'
        );
        $this->assertGreaterThanOrEqual(
            2, substr_count($html, 'QUISPE MAMANI MARIA'),
            'La codeudora debe tener bloque de datos y juego de firma'
        );
        $this->assertStringContainsString('45678912', $html);
        $this->assertStringContainsString('41239876', $html);
    }

    /** C4: dos deudoras F+F, custodia sin GPS, desembolso a tercero */
    public function test_c4_dos_deudoras_custodia_deposito_a_tercero(): void
    {
        $garantia = $this->mundo([
            'sexo' => 'F', 'codeudor_sexo' => 'F',
            'gps' => false, 'custodia' => true,
        ]);

        $html = $this->previsualizar($garantia, $this->parametros([
            'destino' => 'tercero',
            'tercero' => $this->tercero(),
        ]));

        $this->assertStringContainsString('LAS DEUDORAS', $html);

        // Sin GPS, la custodia ocupa la posición 9
        $ord = new Ordinales(ContratoViewModelFactory::clausulas(gps: false, custodia: true));
        $this->assertSame('NOVENO', $ord->de('custodia'));
        $this->assertStringContainsString('NOVENO: CUSTODIA', $html);

        // La constancia consigna al tercero autorizado con cuenta y motivo
        $this->assertStringContainsString('CARLOS ALBERTO RIOS VEGA', $html);
        $this->assertStringContainsString('191-98765432-0-55', $html);
        $this->assertStringContainsString('NO CUENTA CON CUENTA BANCARIA ACTIVA A SU NOMBRE', $html);
    }

    /** C5: persona jurídica con GPS, desembolso a la cuenta del gerente */
    public function test_c5_persona_juridica_gps_destino_gerente(): void
    {
        $garantia = $this->mundo(['tipo_persona' => 'juridica']);

        $html = $this->previsualizar($garantia, $this->parametros([
            'empresa' => $this->empresa(),
            'destino' => 'gerente',
        ]));

        $this->assertStringContainsString('RUC', $html);
        $this->assertStringContainsString('20601234567', $html);
        $this->assertStringContainsString('TRANSPORTES ANDINOS S.A.C.', $html);

        // El gerente aparece al menos dos veces: firma por la empresa y constancia del depósito
        $this->assertGreaterThanOrEqual(
            2, substr_count($html, 'PEDRO PABLO SALAS ROJAS'),
            'El gerente debe aparecer en las firmas y en la constancia de entrega'
        );
    }

    /** C6: deudor con custodia sin GPS y ÚNICO bien futuro → declaración con kardex y notario */
    public function test_c6_bien_futuro_consigna_kardex_y_notario(): void
    {
        $garantia = $this->mundo([
            'gps' => false, 'custodia' => true, 'bien' => 'futuro',
        ]);

        $html = $this->previsualizar($garantia, $this->parametros());

        $this->assertStringContainsString('KARDEX-0373-2026', $html);
        $this->assertStringContainsString('NOTARIO RICARDO FERNANDINI', $html);
    }

    /**
     * C7: caso máximo — jurídica con gerente mujer, GPS + custodia, 2 bienes
     * (presente + futuro), desembolso a tercero. Previsualiza sin excepción y
     * EMITE de verdad: PDF en storage, fila en contratos con snapshot, hash y
     * numeración correlativa; regenerar produce versión 2 con número nuevo.
     */
    public function test_c7_caso_completo_emite_y_versiona(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('legal/vouchers/voucher.png', base64_decode(self::PNG_1X1));

        $garantia = $this->mundo([
            'tipo_persona' => 'juridica',
            'gps' => true, 'custodia' => true,
            'segundo_bien' => 'futuro',
        ]);

        $params = $this->parametros([
            'empresa' => $this->empresa('F', 'ROSA ELENA TORRES DIAZ'),
            'destino' => 'tercero',
            'tercero' => $this->tercero(),
        ]);

        // Previsualización: render sin excepción con ambas cláusulas condicionales
        $html = $this->previsualizar($garantia, $params);
        $this->assertStringContainsString('DISPOSITIVO GPS', $html);
        $this->assertStringContainsString('CUSTODIA', $html);

        // Emisión real
        $this->actingAs($this->user);
        $paramsEmision = array_merge($params, ['voucher' => $this->voucherCompleto()]);

        $contrato = (new GeneradorContrato)->generar($garantia, $paramsEmision);

        $this->assertSame(1, Contrato::count());
        $this->assertSame(1, (int) $contrato->version);
        $this->assertMatchesRegularExpression('/^CGM \d{4}-\d{4}$/', $contrato->numero);
        Storage::disk('public')->assertExists($contrato->pdf_path);
        $this->assertSame(
            hash('sha256', Storage::disk('public')->get($contrato->pdf_path)),
            $contrato->sha256,
            'El sha256 guardado debe corresponder al archivo emitido'
        );

        // Regenerar JAMÁS pisa el PDF emitido: versión nueva y número nuevo
        $segundo = (new GeneradorContrato)->generar($garantia, $paramsEmision);

        $this->assertSame(2, Contrato::count());
        $this->assertSame(2, (int) $segundo->version);
        $this->assertNotSame($contrato->numero, $segundo->numero);
    }

    /** C8: dos deudores M+M con GPS y custodia → la prohibición se renumera al final */
    public function test_c8_gps_y_custodia_renumeran_la_prohibicion(): void
    {
        $garantia = $this->mundo([
            'sexo' => 'M', 'codeudor_sexo' => 'M',
            'gps' => true, 'custodia' => true,
        ]);

        $html = $this->previsualizar($garantia, $this->parametros());

        $this->assertStringContainsString('LOS DEUDORES', $html);
        $this->assertStringContainsString('DISPOSITIVO GPS', $html);
        $this->assertStringContainsString('CUSTODIA', $html);

        // Con GPS + custodia la prohibición cae en la posición 18 (calculado)
        $ord = new Ordinales(ContratoViewModelFactory::clausulas(gps: true, custodia: true));
        $this->assertSame(18, $ord->numero('prohibicion'));
        $this->assertSame('DÉCIMO OCTAVO', $ord->de('prohibicion'));
        $this->assertStringContainsString($ord->de('prohibicion').': PROHIBICIÓN', $html);
    }
}
