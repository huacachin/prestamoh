<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Livewire\Clients\Edit;
use App\Livewire\Clients\Gallery;
use App\Livewire\Clients\GpsVehiculos;
use App\Livewire\Clients\Prestamos;
use App\Livewire\Clients\RutaExpediente as BarraRuta;
use App\Livewire\Clients\Vehiculos;
use App\Livewire\Credits\Show as FichaCredito;
use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\Credit;
use App\Models\CreditVehiculo;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\RutaExpediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 20/11/2026 (Antony): "un flujo de creación de clientes hasta la generación de sus documentos… que
 * les sea amigable". La ficha muestra la ruta del expediente —Cliente → Vehículo → Préstamo → Documentos
 * → SIGM → GPS → Adjuntos— calculada con lo ya guardado; solo orienta, no bloquea. Los adjuntos van al
 * final desde el 21/11 (Antony: "en el paso a paso los adjuntos deben de ir al final").
 */
class RutaExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private User $asesor;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede Ruta', 'status' => 'active']);
        $this->asesor = User::factory()->create(['username' => 'asesor-ruta', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->asesor);
    }

    private function cliente(string $tipoCredito = 'SIGM.S'): Client
    {
        return Client::create(['nombre' => 'Cliente Ruta', 'zona' => $tipoCredito, 'asesor_id' => $this->asesor->id]);
    }

    private function vehiculo(Client $client, string $placa = 'RUT001'): Vehiculo
    {
        return Vehiculo::create(['client_id' => $client->id, 'placa' => $placa]);
    }

    private function credito(Client $client, string $situacion = 'Activo', string $fecha = '2026-11-20'): Credit
    {
        return Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => $fecha, 'importe' => 1000, 'cuotas' => 10,
            'tipo_planilla' => 1, 'interes' => 20, 'interes_total' => 200, 'situacion' => $situacion, 'estado' => 1,
        ]);
    }

    private function documento(Credit $credit, string $tipo, string $estado = 'emitido'): void
    {
        DB::table('documentos_cliente')->insert([
            'client_id' => $credit->client_id, 'credit_id' => $credit->id, 'tipo' => $tipo, 'estado' => $estado,
            'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function garantia(Credit $credit, ?string $estadoDelAviso): void
    {
        $garantia = DB::table('garantias')->insertGetId(['credit_id' => $credit->id, 'client_id' => $credit->client_id, 'created_at' => now(), 'updated_at' => now()]);
        if ($estadoDelAviso !== null) {
            DB::table('sigm_avisos')->insert(['garantia_id' => $garantia, 'tipo' => 'constitucion', 'fecha_presentacion' => '2026-11-20', 'estado' => $estadoDelAviso, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return array<string, string> paso => estado */
    private function estados(Client $client): array
    {
        return array_column(RutaExpediente::de($client)['pasos'], 'estado', 'clave');
    }

    public function test_un_cliente_nuevo_con_garantia_mobiliaria_tiene_todo_pendiente_y_sigue_el_vehiculo(): void
    {
        $ruta = RutaExpediente::de($this->cliente('SIGM.S'));

        $this->assertSame([
            'cliente' => 'hecho', 'vehiculo' => 'pendiente', 'prestamo' => 'pendiente', 'documentos' => 'pendiente',
            'sigm' => 'pendiente', 'gps' => 'pendiente', 'adjuntos' => 'pendiente',
        ], array_column($ruta['pasos'], 'estado', 'clave'));
        $this->assertSame([1, 7], [$ruta['hechos'], $ruta['total']]);
        $this->assertSame(['clave' => 'vehiculo', 'nombre' => 'Vehículo', 'tab' => 'vehiculos', 'accion' => 'registrar o elegir el vehículo'], $ruta['siguiente']);
        $this->assertFalse($ruta['completa']);
    }

    public function test_la_ruta_avanza_con_lo_que_se_va_registrando_hasta_quedar_completa(): void
    {
        $client = $this->cliente();
        $siguiente = fn () => RutaExpediente::de($client)['siguiente']['clave'] ?? null;
        $detalle = fn (string $paso) => array_column(RutaExpediente::de($client)['pasos'], 'detalle', 'clave')[$paso];

        $vehiculo = $this->vehiculo($client);
        $this->assertSame('prestamo', $siguiente());

        $credit = $this->credito($client);
        $this->assertSame('documentos', $siguiente());
        $this->assertSame('#'.$credit->id, $detalle('prestamo'));
        $this->assertSame('0 de 5', $detalle('documentos'));

        $this->documento($credit, 'contrato');
        $this->documento($credit, 'anexo1');
        $this->documento($credit, 'anexo1'); // otra versión del mismo documento no suma
        $this->assertSame('2 de 5', $detalle('documentos'));
        $this->assertSame('documentos', $siguiente());

        foreach (['anexo2', 'djprop', 'djpubli'] as $tipo) {
            $this->documento($credit, $tipo);
        }
        $this->assertSame('sigm', $siguiente());

        $garantia = DB::table('garantias')->insertGetId(['credit_id' => $credit->id, 'client_id' => $client->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sigm_avisos')->insert(['garantia_id' => $garantia, 'tipo' => 'constitucion', 'fecha_presentacion' => '2026-11-20', 'estado' => 'registrado', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('gps', $siguiente());
        $this->assertSame('0 de 1 placa', $detalle('gps'));

        DB::table('vehiculo_gps_cuentas')->insert(['vehiculo_id' => $vehiculo->id, 'aplicativo' => 'Plataforma', 'usuario' => 'usuario', 'contrasena' => 'cifrada', 'created_at' => now(), 'updated_at' => now()]);
        // Los adjuntos son el último paso: se suben cuando el préstamo ya existe.
        $this->assertSame('adjuntos', $siguiente());
        $this->assertSame('Sin fotos', $detalle('adjuntos'));

        DB::table('client_attachments')->insert(['client_id' => $client->id, 'filename' => 'dni.jpg', 'path' => 'clients/dni.jpg', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('1 foto', $detalle('adjuntos'));
        $ruta = RutaExpediente::de($client);
        $this->assertNull($ruta['siguiente']);
        $this->assertTrue($ruta['completa']);
        $this->assertSame([7, 7], [$ruta['hechos'], $ruta['total']]);
    }

    public function test_un_documento_anulado_y_un_aviso_sigm_anulado_no_cuentan(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);
        $this->documento($credit, 'contrato', 'anulado');
        $garantia = DB::table('garantias')->insertGetId(['credit_id' => $credit->id, 'client_id' => $client->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sigm_avisos')->insert(['garantia_id' => $garantia, 'tipo' => 'constitucion', 'fecha_presentacion' => '2026-11-20', 'estado' => 'anulado', 'created_at' => now(), 'updated_at' => now()]);

        $ruta = RutaExpediente::de($client);

        $this->assertSame('0 de 5', array_column($ruta['pasos'], 'detalle', 'clave')['documentos']);
        $this->assertSame('pendiente', $this->estados($client)['sigm']);
    }

    public function test_los_documentos_son_los_del_credito_activo_mas_reciente(): void
    {
        $client = $this->cliente();
        $cancelado = $this->credito($client, 'Cancelado', '2026-01-10');
        $this->documento($cancelado, 'contrato');
        $anterior = $this->credito($client, 'Activo', '2026-06-01');
        $this->documento($anterior, 'contrato');
        $reciente = $this->credito($client, 'Activo', '2026-11-20');

        $ruta = RutaExpediente::de($client);

        $this->assertSame($reciente->id, $ruta['credito']);
        $this->assertSame('0 de 5', array_column($ruta['pasos'], 'detalle', 'clave')['documentos']);
    }

    public function test_sin_credito_activo_el_prestamo_queda_pendiente_aunque_tenga_cancelados(): void
    {
        $client = $this->cliente();
        $this->credito($client, 'Cancelado');

        $ruta = RutaExpediente::de($client);

        $this->assertNull($ruta['credito']);
        $this->assertSame('pendiente', $this->estados($client)['prestamo']);
    }

    public function test_un_tipo_de_credito_sin_garantia_mobiliaria_no_pide_vehiculo_documentos_sigm_ni_gps(): void
    {
        $client = $this->cliente('Sin Garantia');

        $ruta = RutaExpediente::de($client);

        $this->assertSame([
            'cliente' => 'hecho', 'vehiculo' => 'no_aplica', 'prestamo' => 'pendiente', 'documentos' => 'no_aplica',
            'sigm' => 'no_aplica', 'gps' => 'no_aplica', 'adjuntos' => 'pendiente',
        ], array_column($ruta['pasos'], 'estado', 'clave'));
        $this->assertSame([1, 3], [$ruta['hechos'], $ruta['total']]);
        $this->assertSame('prestamo', $ruta['siguiente']['clave']);
    }

    public function test_lo_ya_registrado_cuenta_aunque_el_tipo_de_credito_no_lleve_garantia_mobiliaria(): void
    {
        $client = $this->cliente('Gar. Hip.S');
        $this->vehiculo($client);
        $this->documento($this->credito($client), 'contrato');

        $estados = $this->estados($client);

        $this->assertSame('hecho', $estados['vehiculo']);
        // Con un documento emitido el paso ya se sigue: le faltan los demás.
        $this->assertSame('pendiente', $estados['documentos']);
        $this->assertSame('pendiente', $estados['sigm']);
        $this->assertSame('no_aplica', $estados['gps']);
    }

    public function test_el_tipo_de_credito_se_reconoce_sin_mayusculas_tildes_ni_espacios_de_mas(): void
    {
        $this->assertTrue(RutaExpediente::conGarantiaMobiliaria('SIGM.M'));
        $this->assertTrue(RutaExpediente::conGarantiaMobiliaria('  Garantía Mobiliaria '));
        // 20/11 (Antony): "Cred. Vehicular" también pide la garantía; los demás tipos, no.
        $this->assertTrue(RutaExpediente::conGarantiaMobiliaria('Cred. Vehicular-Rojo 14/07'));
        $this->assertFalse(RutaExpediente::conGarantiaMobiliaria('Gar. Hip.S'));
        $this->assertFalse(RutaExpediente::conGarantiaMobiliaria('Sin Garantia'));
        $this->assertFalse(RutaExpediente::conGarantiaMobiliaria(null));
    }

    public function test_las_rutas_de_varios_clientes_salen_con_las_mismas_consultas_que_la_de_uno(): void
    {
        $clientes = [$this->cliente(), $this->cliente(), $this->cliente('Sin Garantia')];
        foreach ($clientes as $client) {
            $this->vehiculo($client, 'P'.$client->id);
            $this->documento($this->credito($client), 'contrato');
        }
        $contar = function (array $ids): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            RutaExpediente::deClientes($ids);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $paraUno = $contar([$clientes[0]->id]);
        $paraTres = $contar(array_map(fn (Client $c) => $c->id, $clientes));

        $this->assertSame($paraUno, $paraTres);
        $rutas = RutaExpediente::deClientes([$clientes[1]->id, $clientes[2]->id, 999999]);
        $this->assertSame([$clientes[1]->id, $clientes[2]->id], array_keys($rutas), 'un cliente que no existe no trae ruta');
    }

    /**
     * 20/11 (Antony: "ya existe un wizard de vehículo… pide otra vez vehículo, es confuso"): en el alta la
     * ruta sigue al wizard, cuyos dos pasos son los dos primeros de la ruta.
     */
    public function test_en_el_alta_la_ruta_empieza_por_los_datos_del_cliente(): void
    {
        $ruta = RutaExpediente::enAlta(1, null, 0);

        $this->assertSame('cliente', $ruta['actual']);
        $this->assertSame([0, 7], [$ruta['hechos'], $ruta['total']]);
        $this->assertStringContainsString('Paso 1: los datos del cliente', $ruta['aviso']);
    }

    public function test_en_el_paso_de_vehiculos_del_alta_la_ruta_esta_en_el_vehiculo_y_avisa_que_se_puede_dejar_para_la_ficha(): void
    {
        $ruta = RutaExpediente::enAlta(2, 'SIGM.S', 0);
        $pasos = array_column($ruta['pasos'], null, 'clave');

        $this->assertSame('vehiculo', $ruta['actual']);
        $this->assertSame(['hecho', 'Datos listos'], [$pasos['cliente']['estado'], $pasos['cliente']['detalle']]);
        $this->assertSame(['pendiente', 'Sin vehículos'], [$pasos['vehiculo']['estado'], $pasos['vehiculo']['detalle']]);
        $this->assertStringContainsString('su ficha te lo pedirá como el paso que sigue', $ruta['aviso']);
    }

    public function test_un_vehiculo_escrito_en_el_alta_no_se_da_por_hecho_hasta_guardar(): void
    {
        $ruta = RutaExpediente::enAlta(2, 'SIGM.S', 2);
        $vehiculo = array_column($ruta['pasos'], null, 'clave')['vehiculo'];

        $this->assertSame(['pendiente', '2 por guardar'], [$vehiculo['estado'], $vehiculo['detalle']]);
        $this->assertSame(1, $ruta['hechos']);
        $this->assertStringContainsString('se guarda junto con el cliente', $ruta['aviso']);
    }

    public function test_en_el_alta_de_un_tipo_de_credito_sin_garantia_mobiliaria_el_vehiculo_no_aplica_salvo_que_se_agregue(): void
    {
        $sinVehiculo = RutaExpediente::enAlta(2, 'Sin Garantia', 0);
        $conVehiculo = RutaExpediente::enAlta(2, 'Sin Garantia', 1);

        $this->assertSame('no_aplica', array_column($sinVehiculo['pasos'], 'estado', 'clave')['vehiculo']);
        $this->assertSame([1, 3], [$sinVehiculo['hechos'], $sinVehiculo['total']]);
        $this->assertStringContainsString('no pide vehículo', $sinVehiculo['aviso']);
        $this->assertSame('pendiente', array_column($conVehiculo['pasos'], 'estado', 'clave')['vehiculo']);
    }

    public function test_la_barra_de_la_ficha_muestra_el_avance_y_se_recalcula_cuando_una_pestana_avisa(): void
    {
        $client = $this->cliente();

        $barra = Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => 'datos'])
            ->assertSee('Ruta del expediente')
            ->assertSee('1 de 7 pasos')
            ->assertSee('Falta registrar o elegir el vehículo.')
            ->assertSee('Ir a ese paso');

        $this->vehiculo($client);

        $barra->dispatch('expediente-actualizado')
            ->assertSee('2 de 7 pasos')
            ->assertSee('Falta crear el préstamo.');
    }

    public function test_en_la_pestana_del_paso_que_sigue_la_barra_lo_dice_y_no_repite_el_boton(): void
    {
        $client = $this->cliente();

        Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => 'vehiculos'])
            ->assertSee('Estás en el paso que falta: registrar o elegir el vehículo.')
            ->assertDontSee('Ir a ese paso');
    }

    public function test_la_ficha_lleva_la_barra_de_la_ruta_y_confirma_el_registro_al_llegar_desde_el_alta(): void
    {
        $client = $this->cliente();

        Livewire::test(Edit::class, ['id' => $client->id])
            ->assertSee('Ruta del expediente')
            ->assertSee('1 de 7 pasos')
            ->assertDontSee('Cliente registrado');

        session()->flash('client_success', 'Cliente registrado · expediente 9050.');

        Livewire::test(Edit::class, ['id' => $client->id])->assertSee('Cliente registrado · expediente 9050.');
    }

    public function test_el_sigm_cuenta_solo_si_la_garantia_mas_reciente_del_credito_esta_constituida(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);
        $this->garantia($credit, 'registrado');
        $this->assertSame('hecho', $this->estados($client)['sigm']);

        // Una garantía posterior sin constitución es la que manda, como en la ventana SIGM.
        $this->garantia($credit, null);

        $this->assertSame('pendiente', $this->estados($client)['sigm']);
    }

    public function test_guardar_o_eliminar_un_vehiculo_y_borrar_un_adjunto_avisan_a_la_barra_de_la_ruta(): void
    {
        Storage::fake('public');
        $client = $this->cliente();

        $pestana = Livewire::test(Vehiculos::class, ['id' => $client->id])->call('nuevo');
        foreach (['placa' => 'RUT-777', 'marca' => 'Toyota', 'modelo' => 'Hiace', 'nro_motor' => 'M1', 'nro_serie' => 'S1', 'categoria' => 'M2',
            'anio_modelo' => '2020', 'carroceria' => 'Minibús', 'color' => 'Blanco', 'combustible' => 'Diesel', 'valor' => 30000] as $campo => $valor) {
            $pestana->set($campo, $valor);
        }
        $pestana->call('guardar')->assertHasNoErrors()->assertDispatched('expediente-actualizado');

        Livewire::test(Vehiculos::class, ['id' => $client->id])
            ->call('eliminar', Vehiculo::where('client_id', $client->id)->sole()->id)
            ->assertDispatched('expediente-actualizado');
        $this->assertSame(0, Vehiculo::where('client_id', $client->id)->count());

        $adjunto = ClientAttachment::create(['client_id' => $client->id, 'filename' => 'dni.jpg', 'path' => "clients/{$client->id}/dni.jpg"]);

        Livewire::test(Gallery::class, ['id' => $client->id])
            ->call('deleteAttachment', $adjunto->id)
            ->assertDispatched('expediente-actualizado');
        $this->assertNull(ClientAttachment::find($adjunto->id));
    }

    public function test_la_ficha_de_una_persona_relacionada_no_lleva_ruta(): void
    {
        $copropietario = Client::create(['nombre' => 'Copropietario', 'es_relacionado' => true, 'asesor_id' => $this->asesor->id]);

        Livewire::test(BarraRuta::class, ['id' => $copropietario->id])->assertDontSee('Ruta del expediente');
    }

    public function test_a_un_cliente_cesado_sin_prestamo_la_barra_le_dice_que_hay_que_darlo_de_alta(): void
    {
        $cesado = Client::create(['nombre' => 'Cesado', 'zona' => 'SIGM.S', 'status' => 'inactive', 'asesor_id' => $this->asesor->id]);

        Livewire::test(BarraRuta::class, ['id' => $cesado->id])
            ->assertSee('Cliente cesado: dalo de alta en Cliente Cesados para continuar con su expediente.')
            ->assertDontSee('Siguiente:');
    }

    public function test_las_declaraciones_juradas_de_la_lista_esperan_al_contrato(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);
        $tarjeta = fn (string $html, string $tipo): string => explode('</div>', explode('wire:key="lista-doc-'.$tipo.'"', $html)[1])[0];

        $html = Livewire::test(Documentos::class, ['id' => $client->id])->html();
        foreach (['djprop', 'djpubli'] as $tipo) {
            $this->assertStringContainsString('Después del contrato', $tarjeta($html, $tipo));
            $this->assertStringNotContainsString('wire:click=', $tarjeta($html, $tipo), 'sin contrato no se ofrece emitirla');
        }
        $this->assertStringContainsString("emitirDeLaLista('anexo1')", $tarjeta($html, 'anexo1'), 'lo que no depende del contrato sí se ofrece');

        $this->documento($credit, 'contrato');

        $html = Livewire::test(Documentos::class, ['id' => $client->id])->html();
        $this->assertStringContainsString("emitirDeLaLista('djprop')", $tarjeta($html, 'djprop'));
    }

    public function test_el_boton_emitir_de_la_lista_abre_el_modal_con_el_prestamo_de_la_lista_elegido(): void
    {
        // Dos préstamos activos: el de la lista es el más reciente por fecha, aunque el otro tenga el id mayor.
        $client = $this->cliente();
        $enCurso = $this->credito($client, 'Activo', '2026-11-20');
        $otro = $this->credito($client, 'Activo', '2026-06-01');
        $this->documento($enCurso, 'contrato');
        $this->documento($otro, 'contrato');
        $this->assertGreaterThan($enCurso->id, $otro->id);

        // Con dos préstamos la lista deja elegir; arranca en el de la ruta.
        $pestana = Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertSet('listaCreditoId', $enCurso->id)
            ->assertSee('#'.$enCurso->id.' · 20/11/2026 · 1 de 5')
            ->assertSee('#'.$otro->id.' · 01/06/2026 · 1 de 5');

        $pestana->call('emitirDeLaLista', 'contrato')->assertDispatched('contrato-modal-open')->assertSet('contratoCreditoId', $enCurso->id);
        $pestana->call('emitirDeLaLista', 'anexo1')->assertDispatched('anexo1-modal-open')->assertSet('creditoId', $enCurso->id);
        $pestana->call('emitirDeLaLista', 'anexo2')->assertDispatched('anexo2-modal-open')->assertSet('anexo2CreditoId', $enCurso->id);
        // La declaración jurada sola habría elegido el contrato de id mayor.
        $pestana->call('abrirModalDeclaracion', 'djprop')->assertSet('djCreditoId', $otro->id);
        $pestana->call('emitirDeLaLista', 'djprop')->assertDispatched('declaracion-modal-open')->assertSet('djCreditoId', $enCurso->id);

        // Elegido el otro préstamo, la lista y sus botones pasan a ser los de ese.
        $this->documento($otro, 'anexo1');
        $pestana->set('listaCreditoId', $otro->id)
            ->assertSee('2 de 5 emitidos')
            ->call('emitirDeLaLista', 'anexo2')->assertSet('anexo2CreditoId', $otro->id);
        // Un préstamo nuevo desde la pestaña Préstamos devuelve la lista al de la ruta.
        $pestana->dispatch('prestamo-creado', id: $enCurso->id)->assertSet('listaCreditoId', $enCurso->id);
    }

    public function test_una_refinanciacion_no_abre_expediente_la_ruta_sigue_al_prestamo_que_no_lo_es(): void
    {
        $client = $this->cliente();
        $original = $this->credito($client, 'Activo', '2026-09-15');
        $refinanciacion = $this->credito($client, 'Activo', '2026-11-10');
        $refinanciacion->update(['refinanciado' => true]);

        $ruta = RutaExpediente::de($client);

        $this->assertSame($original->id, $ruta['credito'], 'manda el préstamo que no es refinanciación, aunque sea anterior');
        $this->assertSame('pendiente', $this->estados($client)['documentos']);
        Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertSee('Documentos del préstamo #'.$original->id)
            ->assertDontSee('#'.$refinanciacion->id.' · ');
    }

    public function test_a_un_cliente_que_solo_tiene_refinanciaciones_no_se_le_piden_documentos_ni_sigm(): void
    {
        $client = $this->cliente();
        $refinanciacion = $this->credito($client);
        $refinanciacion->update(['refinanciado' => true]);

        $estados = $this->estados($client);

        $this->assertSame($refinanciacion->id, RutaExpediente::de($client)['credito']);
        // La refinanciación no abre expediente: sigue siendo el del préstamo original, nada se vuelve a pedir.
        $this->assertSame([
            'cliente' => 'hecho', 'vehiculo' => 'no_aplica', 'prestamo' => 'hecho', 'documentos' => 'no_aplica',
            'sigm' => 'no_aplica', 'gps' => 'no_aplica', 'adjuntos' => 'no_aplica',
        ], $estados);
        Livewire::test(Documentos::class, ['id' => $client->id])->assertDontSee('Documentos del préstamo');

        // Si el área decide que sí lleva documentos propios, es una línea de configuración.
        config(['expediente.refinanciacion_pide_documentos' => true]);

        $this->assertSame('pendiente', $this->estados($client)['documentos']);
    }

    public function test_el_aviso_de_prestamo_creado_en_la_ficha_del_credito_lleva_a_seguir_con_el_expediente(): void
    {
        $this->asesor->givePermissionTo(Permission::findOrCreate('clientes', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $client = $this->cliente();
        $credit = $this->credito($client);

        Livewire::test(FichaCredito::class, ['id' => $credit->id])->assertDontSee('Seguir con el expediente');

        session()->flash('credit_success', 'Crédito #'.$credit->id.' creado.');

        Livewire::test(FichaCredito::class, ['id' => $credit->id])
            ->assertSee('Seguir con el expediente: Vehículo')
            ->assertSeeHtml('href="'.e(route('clients.edit', ['id' => $client->id, 'prestamo' => $credit->id, 'tab' => 'vehiculos'])).'"');
    }

    // ───── 20/11, tras hablar con el cliente: el paso a paso es de cada préstamo y se recorre adelante y atrás ─────

    public function test_la_ruta_se_calcula_para_el_prestamo_que_se_pida(): void
    {
        $client = $this->cliente();
        $reciente = $this->credito($client, 'Activo', '2026-11-20');
        $anterior = $this->credito($client, 'Activo', '2026-10-01');
        $this->documento($anterior, 'contrato');
        $documentos = fn (array $ruta) => array_column($ruta['pasos'], 'detalle', 'clave')['documentos'];

        $this->assertSame([$reciente->id, '0 de 5'], [RutaExpediente::de($client)['credito'], $documentos(RutaExpediente::de($client))]);
        $delAnterior = RutaExpediente::de($client, $anterior->id);
        $this->assertSame([$anterior->id, '1 de 5'], [$delAnterior['credito'], $documentos($delAnterior)]);
        $porPrestamo = RutaExpediente::dePrestamos([$anterior->id, $reciente->id]);
        $this->assertSame(['1 de 5', '0 de 5'], [$documentos($porPrestamo[$anterior->id]), $documentos($porPrestamo[$reciente->id])]);

        // Un préstamo de otro cliente, cancelado o que no existe: se cae al de la ruta.
        $ajeno = $this->credito($this->cliente());
        $cancelado = $this->credito($client, 'Cancelado', '2026-11-21');
        foreach ([$ajeno->id, $cancelado->id, 999999] as $otro) {
            $this->assertSame($reciente->id, RutaExpediente::de($client, $otro)['credito']);
        }
    }

    public function test_con_varios_prestamos_activos_la_barra_deja_elegir_de_cual_se_ven_los_pasos(): void
    {
        $client = $this->cliente();
        $unico = $this->credito($client, 'Activo', '2026-11-20');

        Livewire::test(BarraRuta::class, ['id' => $client->id])
            ->assertSee('Pasos del préstamo #'.$unico->id)
            ->assertDontSeeHtml('id="ruta-prestamo"');

        $anterior = $this->credito($client, 'Activo', '2026-10-01');
        $this->documento($anterior, 'contrato');

        Livewire::test(BarraRuta::class, ['id' => $client->id])
            ->assertSeeHtml('id="ruta-prestamo"')
            ->assertSee('#'.$unico->id.' · 20/11/2026')
            ->assertSee('#'.$anterior->id.' · 01/10/2026')
            ->assertSeeHtml('title="Documentos: 0 de 5"');
        Livewire::test(BarraRuta::class, ['id' => $client->id, 'prestamo' => $anterior->id])
            ->assertSeeHtml('title="Documentos: 1 de 5"');
    }

    public function test_anterior_y_siguiente_recorren_los_pasos_en_orden_esten_completos_o_no(): void
    {
        $client = $this->cliente();
        $enPantalla = fn (string $tab): string => Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => $tab])->html();
        $irA = fn (string $tab): string => "wire:click=\"\$parent.\$set('tab', '{$tab}')\"";

        // En el primero no hay anterior; el siguiente es el que viene, no "el que falta".
        $html = $enPantalla('datos');
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*<i class="ti ti-arrow-left"><\/i> Anterior\s*<\/button>/', $html);
        $this->assertStringContainsString('Siguiente: Vehículo', $html);

        $html = $enPantalla('prestamos');
        $this->assertStringContainsString('Anterior: Vehículo', $html);
        $this->assertStringContainsString('Siguiente: Documentos', $html);
        $this->assertStringContainsString('Falta registrar o elegir el vehículo.', $html, 'y sigue diciendo qué falta, con el atajo para ir');
        $this->assertStringContainsString('Ir a ese paso', $html);

        // Documentos y SIGM comparten pantalla: de ahí se pasa al GPS y, al final, a los adjuntos.
        $html = $enPantalla('documentos');
        $this->assertStringContainsString('Anterior: Préstamo', $html);
        $this->assertStringContainsString('Siguiente: GPS', $html);
        $this->assertSame(2, substr_count($html, ' es-actual"'), 'Documentos y SIGM, los dos pasos de esa pantalla');
        $html = $enPantalla('gps');
        $this->assertStringContainsString('Anterior: Documentos', $html);
        $this->assertStringContainsString('Siguiente: Adjuntos', $html);
        $html = $enPantalla('adjuntos');
        $this->assertStringContainsString('Anterior: GPS', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*Siguiente\s*<i class="ti ti-arrow-right"><\/i>\s*<\/button>/', $html);
    }

    public function test_anterior_y_siguiente_se_saltan_los_pasos_que_no_aplican(): void
    {
        $client = $this->cliente('Sin Garantia');

        Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => 'datos'])->assertSee('Siguiente: Préstamo');
        Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => 'prestamos'])->assertSee('Siguiente: Adjuntos')->assertDontSee('Siguiente: Documentos');
        Livewire::test(BarraRuta::class, ['id' => $client->id, 'tab' => 'adjuntos'])->assertSee('Anterior: Préstamo');
    }

    public function test_la_ficha_sigue_al_prestamo_recien_creado_y_confirma_el_alta_del_prestamo(): void
    {
        $client = $this->cliente();
        $anterior = $this->credito($client, 'Activo', '2026-10-01');
        $nuevo = $this->credito($client, 'Activo', '2026-09-01'); // más antiguo por fecha: sin elegirlo no sería el de la ruta

        Livewire::test(Edit::class, ['id' => $client->id])
            ->assertSet('prestamo', '')
            ->dispatch('prestamo-creado', id: $nuevo->id)
            ->assertSet('prestamo', (string) $nuevo->id);

        session()->flash('credit_success', 'Crédito #'.$nuevo->id.' creado.');
        Livewire::withQueryParams(['prestamo' => (string) $nuevo->id, 'tab' => 'prestamos'])
            ->test(Edit::class, ['id' => $client->id])
            ->assertSee('Crédito #'.$nuevo->id.' creado.')
            ->assertSeeHtml('wire:key="ruta-prestamo-'.$nuevo->id.'"');
        $this->assertNotSame($nuevo->id, $anterior->id);
    }

    public function test_en_la_ficha_la_lista_de_documentos_es_la_del_prestamo_elegido_en_la_ruta(): void
    {
        $client = $this->cliente();
        $reciente = $this->credito($client, 'Activo', '2026-11-20');
        $anterior = $this->credito($client, 'Activo', '2026-10-01');
        $this->documento($anterior, 'contrato');
        $refinanciacion = $this->credito($client, 'Activo', '2026-11-19');
        $refinanciacion->update(['refinanciado' => true]);

        Livewire::test(Documentos::class, ['id' => $client->id, 'embebido' => true])
            ->assertSee('Documentos del préstamo #'.$reciente->id)
            ->assertDontSeeHtml('id="lista-doc-prestamo"'); // en la ficha el préstamo se elige en la ruta
        Livewire::test(Documentos::class, ['id' => $client->id, 'embebido' => true, 'prestamo' => $anterior->id])
            ->assertSee('Documentos del préstamo #'.$anterior->id)
            ->assertSee('1 de 5 emitidos');
        // A la refinanciación no se le piden documentos: con ella elegida no hay lista.
        Livewire::test(Documentos::class, ['id' => $client->id, 'embebido' => true, 'prestamo' => $refinanciacion->id])
            ->assertDontSee('Documentos del préstamo');
    }

    public function test_la_pestana_prestamos_sombrea_el_prestamo_de_la_ruta_y_deja_ver_los_pasos_de_los_otros(): void
    {
        $this->asesor->givePermissionTo(Permission::findOrCreate('creditos', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $client = $this->cliente();
        $client->update(['documento' => '40112299']);
        $reciente = $this->credito($client, 'Activo', '2026-11-20');
        $anterior = $this->credito($client, 'Activo', '2026-10-01');
        $fila = fn (string $html, Credit $c): string => explode('</tr>', explode('wire:key="prestamo-'.$c->id.'"', $html)[1])[0];
        // 20/11 (Antony): en vez de la etiqueta "en la ruta", la fila va sombreada en amarillo Huacachin.
        $sombreada = fn (string $html, Credit $c): bool => (bool) preg_match('/<tr\b[^>]*class="[^"]*\bfila-en-ruta\b[^"]*"[^>]*wire:key="prestamo-'.$c->id.'"/', $html);
        $verPasos = fn (Credit $c): string => "wire:click=\"\$parent.\$set('prestamo', '{$c->id}')\"";

        $html = Livewire::test(Prestamos::class, ['id' => $client->id])->html();
        $this->assertSame([true, false], [$sombreada($html, $reciente), $sombreada($html, $anterior)]);
        $this->assertStringNotContainsString('>en la ruta</span>', $html, 'ya sin la etiqueta');
        $this->assertStringNotContainsString($verPasos($reciente), $fila($html, $reciente));
        $this->assertStringContainsString($verPasos($anterior), $fila($html, $anterior));

        $html = Livewire::test(Prestamos::class, ['id' => $client->id, 'prestamo' => $anterior->id])->html();
        $this->assertSame([false, true], [$sombreada($html, $reciente), $sombreada($html, $anterior)]);
        $this->assertStringContainsString($verPasos($reciente), $fila($html, $reciente));
    }

    // ───── 20/11 (Antony): "si se crea un préstamo nuevo, la barra sigue como completado cuando en realidad
    //       faltarían del nuevo préstamo" → Vehículo, Adjuntos y GPS también son de cada préstamo ─────

    private function cuentaGps(Vehiculo $vehiculo): void
    {
        DB::table('vehiculo_gps_cuentas')->insert(['vehiculo_id' => $vehiculo->id, 'aplicativo' => 'Plataforma', 'usuario' => 'usuario', 'contrasena' => 'cifrada', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function foto(Client $client): void
    {
        DB::table('client_attachments')->insert(['client_id' => $client->id, 'filename' => 'foto.jpg', 'path' => 'clients/foto.jpg', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, array{0: string, 1: string}> paso => [estado, detalle] */
    private function pasos(Client $client, ?int $creditId = null): array
    {
        $pasos = [];
        foreach (RutaExpediente::de($client, $creditId)['pasos'] as $paso) {
            $pasos[$paso['clave']] = [$paso['estado'], $paso['detalle']];
        }

        return $pasos;
    }

    public function test_un_prestamo_nuevo_vuelve_a_pedir_vehiculo_adjuntos_y_gps_aunque_el_cliente_ya_los_tuviera(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $vehiculo = $this->vehiculo($client);
        $this->foto($client);
        $primero = $this->credito($client, 'Activo', '2026-10-01');
        $this->travelTo('2026-10-03 10:00:00');
        $this->cuentaGps($vehiculo); // el GPS se instala después de dar el préstamo
        $this->assertSame(['hecho', 'hecho', 'hecho'], [$this->estados($client)['vehiculo'], $this->estados($client)['adjuntos'], $this->estados($client)['gps']]);

        $this->travelTo('2026-11-20 10:00:00');
        $nuevo = $this->credito($client, 'Activo', '2026-11-20');

        $pasos = $this->pasos($client);
        $this->assertSame($nuevo->id, RutaExpediente::de($client)['credito']);
        $this->assertSame(['pendiente', 'Elige el vehículo'], [$pasos['vehiculo'][0], $pasos['vehiculo'][1]]);
        $this->assertSame(['pendiente', 'Faltan las de este préstamo'], [$pasos['adjuntos'][0], $pasos['adjuntos'][1]]);
        $this->assertSame(['pendiente', 'Falta el vehículo'], [$pasos['gps'][0], $pasos['gps'][1]]);
        $this->assertSame([2, 7], [RutaExpediente::de($client)['hechos'], RutaExpediente::de($client)['total']]);
        // El préstamo anterior conserva lo suyo.
        $anterior = $this->pasos($client, $primero->id);
        $this->assertSame(['hecho', 'hecho', 'hecho'], [$anterior['vehiculo'][0], $anterior['adjuntos'][0], $anterior['gps'][0]]);
    }

    public function test_el_mismo_vehiculo_se_puede_volver_a_prendar_y_su_gps_hay_que_confirmarlo_para_el_prestamo_nuevo(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $vehiculo = $this->vehiculo($client);
        $this->cuentaGps($vehiculo);
        $this->credito($client, 'Activo', '2026-10-01');
        $this->travelTo('2026-11-20 10:00:00');
        $nuevo = $this->credito($client, 'Activo', '2026-11-20');

        $vinculo = CreditVehiculo::create(['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true]);

        $pasos = $this->pasos($client);
        $this->assertSame(['hecho', '1 vehículo'], $pasos['vehiculo']);
        $this->assertSame(['pendiente', '0 de 1 placa'], $pasos['gps'], 'la cuenta es de antes: falta confirmarla para este préstamo');

        $vinculo->update(['gps_confirmado_at' => now()]);

        $this->assertSame(['hecho', '1 de 1 placa'], $this->pasos($client)['gps']);
    }

    public function test_volver_a_guardar_la_cuenta_gps_durante_el_prestamo_nuevo_tambien_la_da_por_buena(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $vehiculo = $this->vehiculo($client);
        $this->cuentaGps($vehiculo);
        $this->credito($client, 'Activo', '2026-10-01');
        $this->travelTo('2026-11-20 10:00:00');
        $nuevo = $this->credito($client, 'Activo', '2026-11-20');
        CreditVehiculo::create(['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true]);

        DB::table('vehiculo_gps_cuentas')->where('vehiculo_id', $vehiculo->id)->update(['updated_at' => now()]);

        $this->assertSame(['hecho', '1 de 1 placa'], $this->pasos($client)['gps']);
    }

    public function test_lo_registrado_pocos_dias_antes_de_crear_el_prestamo_cuenta_para_ese_prestamo(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $this->vehiculo($client, 'VIEJO1');
        $this->foto($client);
        $this->credito($client, 'Activo', '2026-10-01');

        // Diez días antes del préstamo nuevo: todavía es del anterior. Dos días antes: ya es del nuevo.
        $this->travelTo('2026-11-10 10:00:00');
        $this->foto($client);
        $this->travelTo('2026-11-18 10:00:00');
        $this->foto($client);
        $this->foto($client);
        $this->vehiculo($client, 'NUEVO1');
        $this->travelTo('2026-11-20 10:00:00');
        $nuevo = $this->credito($client, 'Activo', '2026-11-20');

        $pasos = $this->pasos($client, $nuevo->id);
        $this->assertSame(['hecho', '2 fotos'], $pasos['adjuntos']);
        $this->assertSame(['hecho', '1 vehículo'], $pasos['vehiculo']);
        $estados = RutaExpediente::vehiculosDelPrestamo($client->id, $nuevo->id)['vehiculos'];
        $this->assertSame([false, true], array_values(array_column($estados, 'del_prestamo')), 'solo el vehículo registrado para este préstamo');
    }

    public function test_lo_que_vino_del_sistema_anterior_es_de_los_prestamos_antiguos_no_de_uno_nuevo(): void
    {
        $client = $this->cliente();
        $antiguo = $this->credito($client, 'Activo', '2026-03-10');
        $this->foto($client);
        // La migración lo selló todo el 01/09: el préstamo unos minutos ANTES que sus fotos.
        DB::table('credits')->where('id', $antiguo->id)->update(['created_at' => '2026-09-01 06:50:49']);
        DB::table('client_attachments')->where('client_id', $client->id)->update(['created_at' => '2026-09-01 06:58:05']);

        $this->assertSame(['hecho', '1 foto'], $this->pasos($client, $antiguo->id)['adjuntos']);

        $nuevo = $this->credito($client, 'Activo', '2026-11-20');

        $this->assertSame(['pendiente', 'Faltan las de este préstamo'], $this->pasos($client, $nuevo->id)['adjuntos']);
        $this->assertSame(['hecho', '1 foto'], $this->pasos($client, $antiguo->id)['adjuntos']);
    }

    public function test_un_cliente_que_ya_pago_y_vuelve_sin_prestamo_activo_ve_pendiente_lo_del_proximo(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $this->vehiculo($client);
        $this->foto($client);
        $this->credito($client, 'Cancelado', '2026-10-01');
        $this->travelTo('2026-11-20 10:00:00');

        $pasos = $this->pasos($client);
        $this->assertSame(['pendiente', 'Sin préstamo activo'], $pasos['prestamo']);
        $this->assertSame(['pendiente', 'Elige el vehículo'], $pasos['vehiculo']);
        $this->assertSame(['pendiente', 'Faltan las de este préstamo'], $pasos['adjuntos']);

        // Lo que trae ahora cuenta para el préstamo que se le va a dar.
        $this->foto($client);

        $this->assertSame(['hecho', '1 foto'], $this->pasos($client)['adjuntos']);
    }

    /** Cliente con un préstamo de octubre (vehículo, foto y cuenta GPS de entonces) al que hoy se le da otro. */
    private function clienteQueVuelve(): array
    {
        $this->travelTo('2026-10-01 10:00:00');
        $client = $this->cliente();
        $vehiculo = $this->vehiculo($client);
        $this->foto($client);
        $this->cuentaGps($vehiculo);
        $this->credito($client, 'Activo', '2026-10-01');
        $this->travelTo('2026-11-20 10:00:00');

        return [$client, $vehiculo, $this->credito($client, 'Activo', '2026-11-20')];
    }

    public function test_el_vehiculo_que_nombra_el_contrato_emitido_del_prestamo_es_de_ese_prestamo(): void
    {
        [$client, $vehiculo, $nuevo] = $this->clienteQueVuelve();
        $this->assertSame(['pendiente', 'Elige el vehículo'], $this->pasos($client)['vehiculo']);
        $contrato = fn (string $placa, string $estado) => DB::table('documentos_cliente')->insertGetId([
            'client_id' => $client->id, 'credit_id' => $nuevo->id, 'tipo' => 'contrato', 'estado' => $estado,
            'snapshot' => json_encode(['bienes' => [['placa' => $placa]]]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Un contrato anulado, o el de otra placa, no dice nada de este vehículo.
        $contrato('rut-001', 'anulado');
        $contrato('OTRA99', 'emitido');
        $this->assertSame('pendiente', $this->pasos($client)['vehiculo'][0]);

        // La placa se compara sin guiones ni mayúsculas: la ficha dice RUT001 y el contrato "rut-001".
        $this->assertSame('RUT001', $vehiculo->placa);
        $contrato('rut-001', 'emitido');

        $this->assertSame(['hecho', '1 vehículo'], $this->pasos($client)['vehiculo']);
        $this->assertTrue(RutaExpediente::vehiculosDelPrestamo($client->id, $nuevo->id)['vehiculos'][$vehiculo->id]['del_prestamo']);
    }

    public function test_en_la_pestana_vehiculos_se_vuelve_a_prendar_el_mismo_vehiculo_y_se_puede_deshacer(): void
    {
        [$client, $vehiculo, $nuevo] = $this->clienteQueVuelve();

        $pestana = Livewire::test(Vehiculos::class, ['id' => $client->id])
            ->assertSee('todavía no tiene vehículo')
            ->assertSeeHtml('wire:click="prendar('.$vehiculo->id.')"');

        $pestana->call('prendar', $vehiculo->id)
            ->assertDispatched('expediente-actualizado')
            ->assertSee("El vehículo {$vehiculo->placa} garantiza el préstamo #{$nuevo->id}.")
            ->assertSee('préstamo #'.$nuevo->id)
            ->assertSeeHtml('wire:click="quitarPrenda('.$vehiculo->id.')"')
            ->assertDontSeeHtml('wire:click="prendar('.$vehiculo->id.')"');
        $this->assertSame(['hecho', '1 vehículo'], $this->pasos($client)['vehiculo']);
        $this->assertDatabaseHas('credit_vehiculo', ['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true, 'registrado_por' => $this->asesor->id]);

        $pestana->call('quitarPrenda', $vehiculo->id)->assertDispatched('expediente-actualizado');
        $this->assertDatabaseMissing('credit_vehiculo', ['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id]);
        $this->assertSame('pendiente', $this->pasos($client)['vehiculo'][0]);
    }

    public function test_no_se_prenda_el_vehiculo_de_otro_cliente_ni_lo_hace_quien_solo_mira(): void
    {
        [$client, , $nuevo] = $this->clienteQueVuelve();
        $ajeno = $this->vehiculo($this->cliente(), 'AJENO1');

        // Un vehículo que no es de este cliente no existe para esta pestaña: 404 y nada se vincula.
        Livewire::test(Vehiculos::class, ['id' => $client->id])->call('prendar', $ajeno->id)->assertNotFound();
        $this->assertDatabaseMissing('credit_vehiculo', ['credit_id' => $nuevo->id]);

        // El analista (cartera propia) ve la pestaña pero no edita.
        $this->asesor->givePermissionTo(Permission::findOrCreate('clientes.scope-propio', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $propio = Vehiculo::where('client_id', $client->id)->sole();

        Livewire::test(Vehiculos::class, ['id' => $client->id])->call('prendar', $propio->id)->assertForbidden();
        $this->assertDatabaseMissing('credit_vehiculo', ['credit_id' => $nuevo->id]);
    }

    public function test_en_la_pestana_gps_se_confirma_la_cuenta_ya_registrada_para_el_prestamo_nuevo(): void
    {
        [$client, $vehiculo, $nuevo] = $this->clienteQueVuelve();

        // Mientras el vehículo no sea del préstamo nuevo no hay nada que confirmar.
        Livewire::test(GpsVehiculos::class, ['id' => $client->id])
            ->assertDontSeeHtml('wire:click="confirmarCuenta('.$vehiculo->id.')"')
            ->call('confirmarCuenta', $vehiculo->id)
            ->assertDispatched('errorAlert');
        $this->assertDatabaseMissing('credit_vehiculo', ['credit_id' => $nuevo->id]);

        CreditVehiculo::create(['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true]);

        Livewire::test(GpsVehiculos::class, ['id' => $client->id])
            ->assertSee('falta confirmar el GPS')
            ->assertSeeHtml('wire:click="confirmarCuenta('.$vehiculo->id.')"')
            ->call('confirmarCuenta', $vehiculo->id)
            ->assertDispatched('expediente-actualizado')
            ->assertSee('GPS listo')
            ->assertDontSeeHtml('wire:click="confirmarCuenta('.$vehiculo->id.')"');
        $this->assertSame(['hecho', '1 de 1 placa'], $this->pasos($client)['gps']);
        $this->assertDatabaseHas('credit_vehiculo', ['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true, 'gps_confirmado_por' => $this->asesor->id]);
    }

    public function test_guardar_la_cuenta_gps_desde_el_paso_a_paso_la_deja_confirmada_para_ese_prestamo(): void
    {
        [$client, $vehiculo, $nuevo] = $this->clienteQueVuelve();
        CreditVehiculo::create(['credit_id' => $nuevo->id, 'vehiculo_id' => $vehiculo->id, 'prendado' => true]);
        DB::table('vehiculo_gps_cuentas')->where('vehiculo_id', $vehiculo->id)->delete();
        $this->assertSame(['pendiente', '0 de 1 placa'], $this->pasos($client)['gps']);

        Livewire::test(GpsVehiculos::class, ['id' => $client->id])
            ->assertSee('falta la cuenta GPS')
            ->call('abrirCuenta', $vehiculo->id)
            ->set('cuenta.aplicativo', 'Protrack')
            ->set('cuenta.usuario', 'huacachin01')
            ->set('cuenta.contrasena', 'S3creta-GPS')
            ->call('guardarCuenta')
            ->assertHasNoErrors();

        $this->assertSame(['hecho', '1 de 1 placa'], $this->pasos($client)['gps']);
    }

    public function test_la_pestana_adjuntos_avisa_que_las_fotos_que_se_ven_son_de_prestamos_anteriores(): void
    {
        [$client, , $nuevo] = $this->clienteQueVuelve();
        $aviso = "Estas fotos son de préstamos anteriores. Sube las del préstamo #{$nuevo->id} para completar este paso.";

        Livewire::test(Gallery::class, ['id' => $client->id, 'embebido' => true])->assertSee($aviso);

        $this->foto($client);

        Livewire::test(Gallery::class, ['id' => $client->id, 'embebido' => true])->assertDontSee($aviso);
    }

    public function test_el_analista_no_ve_la_ruta_de_un_cliente_de_otra_cartera(): void
    {
        $ajeno = Client::create(['nombre' => 'Cliente Ajeno', 'zona' => 'SIGM.S', 'asesor_id' => User::factory()->create(['username' => 'otro-asesor'])->id]);
        $this->asesor->givePermissionTo(Permission::findOrCreate('clientes.scope-propio', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(BarraRuta::class, ['id' => $ajeno->id])->assertForbidden();
    }

    public function test_la_pestana_documentos_lista_los_del_prestamo_en_curso_en_orden_y_dice_cual_sigue(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);
        $this->documento($credit, 'contrato');
        $this->documento($credit, 'anexo1');

        $html = Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertSee('Documentos del préstamo #'.$credit->id)
            ->assertSee('2 de 5 emitidos')
            ->html();

        // Una tarjeta por documento, en el orden de emisión, y el SIGM al final.
        preg_match_all('/wire:key="lista-doc-([a-z0-9]+)"/', $html, $m);
        $this->assertSame(['contrato', 'anexo1', 'anexo2', 'djprop', 'djpubli', 'sigm'], $m[1]);
        // Lo emitido ya no ofrece emitirse; sigue el Anexo 2 y los demás quedan por emitir (sin bloquear).
        $estados = [];
        foreach ($m[1] as $tipo) {
            // La tarjeta no lleva divs por dentro: termina en su primer cierre.
            $tarjeta = explode('</div>', explode('wire:key="lista-doc-'.$tipo.'"', $html)[1])[0];
            $estados[$tipo] = [
                str_contains($tarjeta, 'Sigue este') ? 'sigue' : (str_contains($tarjeta, 'Por emitir') ? 'por emitir' : (str_contains($tarjeta, 'Emitido') ? 'emitido' : 'otro')),
                str_contains($tarjeta, 'wire:click='),
            ];
        }
        $this->assertSame([
            'contrato' => ['emitido', false], 'anexo1' => ['emitido', false], 'anexo2' => ['sigue', true],
            'djprop' => ['por emitir', true], 'djpubli' => ['por emitir', true], 'sigm' => ['otro', true], // «Sin constituir», con su botón
        ], $estados);
        $this->assertStringContainsString('wire:click="abrirModalSigm('.$credit->id.')"', $html);
    }

    public function test_el_sigm_de_la_lista_espera_al_contrato_y_sigue_cuando_ya_estan_los_documentos(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);

        Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertSee('Después del contrato')
            ->assertDontSeeHtml('wire:click="abrirModalSigm('.$credit->id.')"');

        foreach (['contrato', 'anexo1', 'anexo2', 'djprop', 'djpubli'] as $tipo) {
            $this->documento($credit, $tipo);
        }

        $html = Livewire::test(Documentos::class, ['id' => $client->id])->assertSee('5 de 5 emitidos')->html();
        $sigm = explode('wire:key="lista-doc-sigm"', $html)[1];
        $this->assertStringContainsString('Sigue este', explode('</div>', $sigm)[0]);
    }

    public function test_sin_prestamo_activo_la_lista_de_documentos_pide_crear_el_prestamo_y_no_ofrece_emitir(): void
    {
        $client = $this->cliente();
        $this->credito($client, 'Cancelado');

        Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertSee('Los documentos se emiten sobre un préstamo activo: primero crea el préstamo.')
            ->assertSee('0 de 5 emitidos')
            ->assertDontSee('Sigue este')
            ->assertDontSeeHtml('aria-label="Emitir ');
    }

    public function test_a_un_tipo_de_credito_sin_garantia_mobiliaria_no_se_le_muestra_la_lista_de_documentos(): void
    {
        $client = $this->cliente('Sin Garantia');
        $credit = $this->credito($client);

        Livewire::test(Documentos::class, ['id' => $client->id])
            ->assertDontSee('Documentos del préstamo')
            ->assertSee('Generar Contrato'); // los botones de siempre siguen ahí

        // En cuanto tiene un documento de ese préstamo, la lista aparece con lo que le falta.
        $this->documento($credit, 'contrato');

        Livewire::test(Documentos::class, ['id' => $client->id])->assertSee('1 de 5 emitidos');
    }
}
