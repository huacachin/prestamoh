<?php

namespace Tests\Feature\Legal;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Legal\ValidadorContrato;
use Carbon\Carbon;
use Database\Seeders\LegalSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validación cruzada BLOQUEANTE previa a generar el contrato: cada regla que
 * hoy se pasa por alto en Word (montos que no cuadran con el cronograma real,
 * voucher que no coincide con el desembolso, jurídica sin gerente, bien futuro
 * sin acta/kardex, tercero sin cuenta, fichas incompletas) debe devolver su
 * error con el mensaje esperado — y el mundo completo debe devolver [].
 */
class ValidacionCruzadaTest extends TestCase
{
    use RefreshDatabase;

    private const FECHA = '2026-03-09';

    private Client $client;

    private Vehiculo $vehiculo;

    // ─────────────────────────────── Mundo mínimo ───────────────────────────────

    /**
     * Crédito de S/ 7,000 en 28 cuotas semanales de S/ 320.00 → cronograma
     * real S/ 8,960.00 que cuadra con el monto_gravamen; cliente y vehículo
     * con la ficha completa (el mundo nace VÁLIDO y cada test lo rompe).
     */
    private function mundo(): Garantia
    {
        $user = User::factory()->create(['username' => 'legal-f2-'.uniqid()]);
        $sede = Headquarter::create(['name' => 'Sede Legal']);

        $this->client = Client::create([
            'nombre' => 'JUAN', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'RAMOS',
            'documento' => '45678912', 'sexo' => 'M',
            'estado_civil' => 'SOLTERO', 'ocupacion' => 'COMERCIANTE',
            'direccion' => 'AV. LOS INCAS 123', 'distrito' => 'ATE',
            'email' => 'juan@correo.test',
        ]);

        $credit = Credit::create([
            'client_id' => $this->client->id,
            'fecha_prestamo' => self::FECHA,
            'importe' => 7000, 'cuotas' => 28, 'tipo_planilla' => 1,
            'user_id' => $user->id, 'headquarter_id' => $sede->id,
        ]);

        foreach (range(1, 28) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse(self::FECHA)->addWeeks($n)->toDateString(),
                'importe_cuota' => 250, 'importe_interes' => 70, 'importe_excedente' => 0,
            ]);
        }

        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'ABC-123',
            'marca' => 'TOYOTA', 'modelo' => 'HILUX',
            'nro_motor' => 'MTR-154321', 'nro_serie' => 'SER-198765',
            'valor' => 9000,
        ]);

        $garantia = Garantia::create([
            'credit_id' => $credit->id,
            'client_id' => $this->client->id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural',
            'monto_gravamen' => 8960, // = 28 × 320.00 del cronograma real
            'registrado_por' => $user->id,
        ]);
        $garantia->vehiculos()->attach($this->vehiculo->id, ['orden' => 1]);

        (new LegalSettingsSeeder)->run();

        return $garantia;
    }

    private function validar(Garantia $garantia, array $extra = [], bool $paraEmitir = false): array
    {
        return (new ValidadorContrato)->validar(
            $garantia->fresh(),
            array_merge(['fecha' => self::FECHA], $extra),
            paraEmitir: $paraEmitir
        );
    }

    private function voucherCompleto(array $camposExtra = []): array
    {
        return [
            'banco' => 'bcp',
            'modalidad' => 'transferencia',
            'campos' => array_merge([
                'monto' => '7,000.00',
                'fecha_hora' => '09/03/2026 10:15 AM',
                'beneficiario' => 'PEREZ RAMOS JUAN',
                'cuenta_destino' => '191-98765432-0-55',
                'nro_operacion' => '00123456',
            ], $camposExtra),
            'imagen_path' => 'legal/vouchers/voucher.png',
        ];
    }

    /** Afirma que la lista de errores contiene alguno con el fragmento dado. */
    private function assertConError(array $errores, string $fragmento): void
    {
        $hay = false;
        foreach ($errores as $e) {
            if (str_contains($e, $fragmento)) {
                $hay = true;
                break;
            }
        }

        $this->assertTrue(
            $hay,
            "Se esperaba un error que contenga '{$fragmento}'. Errores: ".
            ($errores === [] ? '(ninguno)' : implode(' | ', $errores))
        );
    }

    // ─────────────────────────────── Reglas ───────────────────────────────

    public function test_monto_gravamen_distinto_al_cronograma_bloquea(): void
    {
        $garantia = $this->mundo();
        $garantia->update(['monto_gravamen' => 9999]); // el cronograma real suma 8,960.00

        $this->assertConError($this->validar($garantia), 'no cuadra');
    }

    public function test_voucher_con_monto_distinto_al_credito_bloquea(): void
    {
        $garantia = $this->mundo();

        $errores = $this->validar($garantia, [
            'voucher' => $this->voucherCompleto(['monto' => '6,500.00']), // el crédito es de 7,000
        ]);

        $this->assertConError($errores, 'no coincide');
    }

    public function test_persona_juridica_sin_gerente_bloquea(): void
    {
        $garantia = $this->mundo();
        $garantia->update(['tipo_persona' => 'juridica']);

        $errores = $this->validar($garantia, [
            'empresa' => [
                'razon_social' => 'TRANSPORTES ANDINOS S.A.C.',
                'ruc' => '20601234567',
                // sin 'gerente'
            ],
        ]);

        $this->assertConError($errores, 'gerente');
    }

    public function test_bien_futuro_sin_kardex_bloquea(): void
    {
        $garantia = $this->mundo();
        $garantia->vehiculos()->updateExistingPivot($this->vehiculo->id, [
            'es_bien_futuro' => true,
            'acta_notarial' => 'ACTA DE TRANSFERENCIA VEHICULAR',
            'kardex' => null, // falta
            'notario' => 'NOTARIO RICARDO FERNANDINI',
        ]);

        $this->assertConError($this->validar($garantia), 'bien futuro');
    }

    public function test_deposito_a_tercero_sin_cuenta_bloquea(): void
    {
        $garantia = $this->mundo();

        $errores = $this->validar($garantia, [
            'destino' => 'tercero',
            'tercero' => [
                'nombre' => 'CARLOS ALBERTO RIOS VEGA',
                'dni' => '43219876',
                // sin 'cuenta'
                'motivo' => 'NO CUENTA CON CUENTA BANCARIA ACTIVA',
            ],
        ]);

        $this->assertConError($errores, 'tercero');
    }

    public function test_cliente_sin_estado_civil_bloquea(): void
    {
        $garantia = $this->mundo();
        $this->client->update(['estado_civil' => null]);

        $this->assertConError($this->validar($garantia), 'estado civil');
    }

    public function test_el_caso_feliz_no_devuelve_errores(): void
    {
        $garantia = $this->mundo();

        $errores = $this->validar(
            $garantia,
            ['voucher' => $this->voucherCompleto()],
            paraEmitir: true
        );

        $this->assertSame([], $errores);
    }
}
