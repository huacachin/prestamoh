<?php

namespace Tests\Feature;

use App\Livewire\Clients\NotificationsModal;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\Garantias;
use App\Support\NumerosEnLetras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Requerimiento final de nivel 3+: la plantilla se reparte según la garantía
 * del cliente (prefijo de clients.zona = "T. Crédito") con las variables en
 * letras (cuotas y monto atrasado).
 */
class NotificacionGarantiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeros_en_letras_con_los_ejemplos_de_las_cartas(): void
    {
        $this->assertSame('DOS MIL QUINIENTOS CON 00/100', NumerosEnLetras::monto(2500.00));
        $this->assertSame('MIL DOSCIENTOS TREINTA Y SEIS CON 50/100', NumerosEnLetras::monto(1236.50));
        $this->assertSame('TRES (03)', NumerosEnLetras::conteo(3));
        $this->assertSame('VEINTIDOS (22)', NumerosEnLetras::conteo(22));
        $this->assertSame('CIENTO CUARENTA Y SIETE CON 00/100', NumerosEnLetras::monto(147.00));
    }

    public function test_clasificador_de_garantias(): void
    {
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('SIGM.S'));
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('SIGM.M-Ambar 14/07'));
        $this->assertSame(Garantias::VEHICULAR, Garantias::de(' SIGM'));
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('Cred. Vehicular-Rojo 13/04'));
        $this->assertSame(Garantias::HIPOTECARIA, Garantias::de('Gar. Hip.S'));
        $this->assertSame(Garantias::HIPOTECARIA, Garantias::de(' Gar. Hip.M-Rojo 05/12/2025'));
        // 05/09: los estados "en ejecución" del catálogo llevan la garantía
        // delante justo para no perder su plantilla legal.
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('SIGM.S-Ejecución'));
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('SIGM.M-Ejecución'));
        $this->assertSame(Garantias::HIPOTECARIA, Garantias::de('Gar. Hip.S-Ejecución'));
        $this->assertSame(Garantias::HIPOTECARIA, Garantias::de('Gar. Hip.M-Ejecución'));
        // Y el valor histórico del legacy, que sigue igual.
        $this->assertSame(Garantias::VEHICULAR, Garantias::de('SIGM.S-Ejecucion 14/01'));
        $this->assertSame(Garantias::OTRA, Garantias::de('Sin Garantia'));
        $this->assertSame(Garantias::OTRA, Garantias::de('Demandado Veh. Cap.-Moto B03163'));
        $this->assertSame(Garantias::OTRA, Garantias::de(null));
        $this->assertSame(Garantias::OTRA, Garantias::de(''));
    }

    private function clienteConCredito(string $zona, string $sexo = 'M'): array
    {
        $client = Client::create([
            'nombre' => 'Victor Raul', 'apellido_pat' => 'Alhuay', 'apellido_mat' => 'Quispe',
            'celular1' => '999888777', 'zona' => $zona, 'sexo' => $sexo,
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(4)->format('Y-m-d'),
            'importe' => 5000, 'cuotas' => 6, 'tipo_planilla' => 3, 'interes' => 20,
            'interes_total' => 1000, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        // 3 cuotas vencidas impagas de 1000 c/u (833.33 cap + 166.67 int) = retraso 3000
        foreach (range(1, 6) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->subMonths(4)->addMonths($n)->format('Y-m-d'),
                'importe_cuota' => 833.33, 'importe_interes' => 166.67, 'pagado' => 0,
            ]);
        }

        return [$client, $credit];
    }

    public function test_vehicular_recibe_el_requerimiento_sigm_con_variables(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client, $credit] = $this->clienteConCredito('SIGM.S');

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');

        $texto = $c->get('texto');
        $this->assertStringContainsString('GARANTIA VEHICULAR SIGM', $texto);
        $this->assertStringContainsString('TOMA DE POSESIÓN DEL VEHÍCULO', $texto);
        $this->assertStringContainsString('VICTOR RAUL ALHUAY QUISPE', $texto);
        // Negritas de WhatsApp (formato del área legal 21/08)
        $this->assertStringContainsString('*TRES (03) CUOTAS VENCIDAS*', $texto);
        $this->assertStringContainsString('*S/ 3,000.00 (TRES MIL CON 00/100)*', $texto);
        $this->assertStringContainsString('*TOMA DE POSESIÓN DEL VEHÍCULO*', $texto);
        $this->assertStringContainsString('*⚖️Abog. Rosa Linda Tafur Cuenca*', $texto);
        $this->assertStringContainsString("Crédito N° {$credit->id}", $texto);
        $this->assertStringContainsString('Estimado sr(a)', $texto);
        $this->assertStringContainsString('debidamente notificado', $texto);
        $this->assertStringContainsString('Abog. Rosa Linda Tafur Cuenca', $texto);
    }

    public function test_hipotecaria_recibe_el_requerimiento_de_remate_en_femenino(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client] = $this->clienteConCredito('Gar. Hip.M', 'F');

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');

        $texto = $c->get('texto');
        $this->assertStringContainsString('GARANTIA HIPOTECARIA', $texto);
        $this->assertStringContainsString('REMATE DEL INMUEBLE', $texto);
        $this->assertStringContainsString('Estimada sr(a)', $texto);
        $this->assertStringContainsString('debidamente notificada', $texto);
        $this->assertStringNotContainsString('VEHÍCULO', $texto);
    }

    /** Con 4+ vencidas el vehicular pasa al comunicado de EJECUCIÓN (24h). */
    public function test_con_cuatro_vencidas_vehicular_recibe_ejecucion_extrajudicial(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client] = $this->clienteConCredito('SIGM.S');
        // La cuota 4 también vencida (4 en total)
        CreditInstallment::where('credit_id', Credit::where('client_id', $client->id)->value('id'))
            ->where('num_cuota', 4)
            ->update(['fecha_vencimiento' => now()->subDay()->format('Y-m-d')]);

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');

        $texto = $c->get('texto');
        $this->assertStringContainsString('COMUNICADO DE EJECUCIÓN EXTRAJUDICIAL - GARANTIA VEHICULAR SIGM', $texto);
        $this->assertStringContainsString('*4(CUATRO) CUOTAS VENCIDAS E IMPAGAS*', $texto);
        $this->assertStringContainsString('VEINTICUATRO (24) HORAS', $texto);
        $this->assertStringContainsString('SOLES)', $texto);
        $this->assertStringNotContainsString('REQUERIMIENTO FINAL', $texto);
    }

    public function test_con_cuatro_vencidas_hipotecaria_recibe_preaviso_judicial(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client] = $this->clienteConCredito('Gar. Hip.M', 'F');
        CreditInstallment::where('credit_id', Credit::where('client_id', $client->id)->value('id'))
            ->where('num_cuota', 4)
            ->update(['fecha_vencimiento' => now()->subDay()->format('Y-m-d')]);

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');

        $texto = $c->get('texto');
        $this->assertStringContainsString('PRE-AVISO DE EJECUCIÓN JUDICIAL - GARANTIA HIPOTECARIA', $texto);
        $this->assertStringContainsString('*04 (CUATRO) CUOTAS VENCIDAS E IMPAGAS*', $texto);
        $this->assertStringContainsString('REMATE JUDICIAL DEL INMUEBLE', $texto);
        $this->assertStringContainsString('debidamente notificada', $texto);
    }

    /** Con 3 EXACTAS sigue el requerimiento final (el escalón anterior). */
    public function test_con_tres_exactas_sigue_el_requerimiento_final(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client] = $this->clienteConCredito('SIGM.S');

        $texto = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif')
            ->get('texto');

        $this->assertStringContainsString('REQUERIMIENTO FINAL', $texto);
        $this->assertStringNotContainsString('EJECUCIÓN EXTRAJUDICIAL - GARANTIA', $texto);
    }

    /**
     * Regresión (02/09): dentro del mismo nivel, un envío previo NO debe
     * pegar el texto viejo. Antes se reutilizaba el último mensaje del nivel
     * y las cuotas/montos quedaban congelados al día del primer envío.
     */
    public function test_nuevo_editor_trae_datos_frescos_aunque_haya_envio_previo_del_mismo_nivel(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client, $credit] = $this->clienteConCredito('SIGM.S');

        // 4 vencidas → comunicado de ejecución; se envía (queda en historial).
        CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 4)
            ->update(['fecha_vencimiento' => now()->subDay()->format('Y-m-d')]);

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');
        $this->assertStringContainsString('4(CUATRO) CUOTAS', $c->get('texto'));
        $c->call('enviarNotif');

        // Cae la 5ta cuota: mismo nivel (4+), pero el editor debe traer CINCO.
        CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 5)
            ->update(['fecha_vencimiento' => now()->subDay()->format('Y-m-d')]);

        $texto = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif')
            ->get('texto');

        $this->assertStringContainsString('5(CINCO) CUOTAS', $texto);
        $this->assertStringNotContainsString('4(CUATRO) CUOTAS', $texto);
    }

    public function test_sin_garantia_conserva_el_comunicado_generico(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client] = $this->clienteConCredito('Sin Garantia');

        $c = Livewire::test(NotificationsModal::class)
            ->call('abrir', $client->id)
            ->call('nuevaNotif');

        $texto = $c->get('texto');
        $this->assertStringContainsString('COMUNICADO DE REGULARIZACIÓN DE PAGO', $texto);
        $this->assertStringNotContainsString('REQUERIMIENTO FINAL', $texto);
    }
}
