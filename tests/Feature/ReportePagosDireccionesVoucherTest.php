<?php

namespace Tests\Feature;

use App\Livewire\Reports\Payments;
use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reporte de pagos (22/10, pedido de Antony): la columna Map "jala" las
 * direcciones del cliente (dirección de la ficha + ubicaciones GPS con su
 * enlace a Google Maps) en una ventana flotante, y cada cobro muestra la foto
 * de su voucher (el adjunto del egreso "Banco" colgado del lote) en el visor
 * movible. Los cobros nuevos no traen coordenadas propias (payments.latitud),
 * que era lo único que enlazaba el Map del legacy.
 */
class ReportePagosDireccionesVoucherTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-10-22';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->travelTo(self::HOY.' 10:00:00');
    }

    private function usuario(bool $admin = true): User
    {
        $user = User::factory()->create(['username' => $admin ? 'admin-rep' : 'cobrador-rep', 'headquarter_id' => 1]);
        if ($admin) {
            $user->givePermissionTo(Permission::findOrCreate('transacciones.autorizar', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    /**
     * Un cobro de hoy: pagos CAPITAL/INTERES del crédito + lote del mismo día.
     *
     * @return array{client: Client, credit: Credit, lote: int}
     */
    private function cobro(array $cliente = [], ?string $latitudPago = null): array
    {
        $client = Client::create(array_merge([
            'nombre' => 'Edwin', 'apellido_pat' => 'Leoncio', 'apellido_mat' => 'Escalera',
            'headquarter_id' => 1, 'status' => 'active',
        ], $cliente));
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25', 'importe' => 10000, 'cuotas' => 48,
            'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 6000, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => 1,
        ]);
        foreach ([['INTERES', 125.00], ['CAPITAL', 208.33]] as [$tipo, $monto]) {
            DB::table('payments')->insert([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
                'fecha' => self::HOY.' 00:00:00', 'hora' => '08:55:42', 'monto' => $monto,
                'detalle' => "Pago : {$credit->id} {$tipo}", 'usuario' => 'Licet', 'asesor' => 'Guilmer',
                'latitud' => $latitudPago, 'longitud' => $latitudPago ? '-77.0428' : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $lote = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 333.33, 'date' => self::HOY, 'time' => '08:55:42',
            'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['client' => $client, 'credit' => $credit, 'lote' => $lote];
    }

    /** El voucher: egreso "Banco" del lote con su foto adjunta. */
    private function voucher(int $lote, string $nombre = 'voucher.jpg', bool $conThumb = true): int
    {
        $egreso = DB::table('expenses')->insertGetId([
            'reason' => 'Banco', 'total' => 333.33, 'date' => self::HOY, 'modo' => 'Otros', 'detail' => 'Dep. Bcp (Yape)',
            'mass_deletion_id' => $lote, 'headquarter_id' => 1, 'caja' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('expense_attachments')->insert([
            'expense_id' => $egreso, 'filename' => $nombre, 'original_name' => 'WhatsApp Image.jpeg',
            'path' => "expenses/{$egreso}/{$nombre}", 'thumb_path' => $conThumb ? "expenses/{$egreso}/thumbs/{$nombre}" : null,
            'mime' => 'image/jpeg', 'size' => 1000, 'uploaded_by' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $egreso;
    }

    public function test_la_fila_trae_las_direcciones_del_cliente_y_la_foto_del_voucher(): void
    {
        $this->usuario();
        ['client' => $client, 'lote' => $lote] = $this->cobro([
            'direccion' => 'Jr. Los Pinos 123', 'referencia' => 'Frente al parque', 'distrito' => 'Ate', 'provincia' => 'Lima', 'zona' => 'Z3',
        ]);
        ClientUbicacion::create(['client_id' => $client->id, 'tipo' => 'casa', 'nombre' => 'Casa', 'latitud' => '-12.0464', 'longitud' => '-77.0428', 'registrado_at' => '2026-10-15 09:00:00']);
        ClientUbicacion::create(['client_id' => $client->id, 'tipo' => 'negocio', 'nombre' => 'Negocio']); // sin coordenadas: no se lista
        ClientUbicacion::create(['client_id' => $client->id, 'tipo' => 'otra', 'nombre' => 'Chacra', 'latitud' => '-12.1', 'longitud' => '-77.1']);
        $egreso = $this->voucher($lote);

        $c = Livewire::test(Payments::class);
        $rows = $c->viewData('rows');

        $this->assertCount(1, $rows, 'un cobro = una fila');
        $dir = $rows[0]['direcciones'];
        $this->assertSame('Leoncio Escalera Edwin', $rows[0]['cliente']);
        $this->assertSame('Jr. Los Pinos 123', $dir['direccion']);
        $this->assertSame('Frente al parque', $dir['referencia']);
        $this->assertSame('Ate · Lima', $dir['lugar']);
        $this->assertSame('Z3', $dir['zona']);
        $this->assertNull($dir['pago'], 'los cobros nuevos no traen coordenadas');
        $this->assertSame(['Casa', 'Chacra'], array_column($dir['ubicaciones'], 'nombre'), 'solo las que tienen coordenadas, Casa primero');
        // El enlace lleva las coordenadas tal como se guardan (decimal(10,7)), igual que en la ficha;
        // el texto que se lee va sin los ceros de relleno.
        $this->assertSame('https://maps.google.com/?q=-12.0464000,-77.0428000', $dir['ubicaciones'][0]['url']);
        $this->assertSame('-12.0464, -77.0428', $dir['ubicaciones'][0]['coordenadas']);
        $this->assertSame('15/10/2026', $dir['ubicaciones'][0]['fecha']);

        $this->assertCount(1, $rows[0]['vouchers']);
        $this->assertSame("/storage/expenses/{$egreso}/voucher.jpg", $rows[0]['vouchers'][0]['url']);
        $this->assertSame("/storage/expenses/{$egreso}/thumbs/voucher.jpg", $rows[0]['vouchers'][0]['thumb']);
        $this->assertSame('Voucher 22/10/2026 08:55 · WhatsApp Image.jpeg', $rows[0]['vouchers'][0]['name']);

        // La pantalla: la cámara de caja (ícono + contador, sin miniatura) con las fotos inline,
        // pin verde con las 2 ubicaciones, la ventana y el visor.
        $c->assertSeeHtml('ti-camera f-s-16 text-info')
            ->assertSee('Ver voucher (1)')
            ->assertDontSeeHtml('<img src="/storage/expenses/')
            ->assertSee('Ver direcciones (2 con GPS)')
            ->assertSeeHtml('ti-map-pin f-s-16')
            ->assertSeeHtml('id="direccionesModal"')
            ->assertSeeHtml('class="huac-lb"');
    }

    public function test_sin_voucher_ni_gps_la_fila_no_inventa_nada(): void
    {
        $this->usuario();
        $this->cobro(['direccion' => 'Av. Sin GPS 9']);

        $c = Livewire::test(Payments::class);
        $rows = $c->viewData('rows');

        $this->assertSame([], $rows[0]['vouchers']);
        $this->assertSame([], $rows[0]['direcciones']['ubicaciones']);
        $this->assertSame('Av. Sin GPS 9', $rows[0]['direcciones']['direccion'], 'la dirección escrita sí se muestra');
        $c->assertDontSee('Ver voucher')
            ->assertSeeHtml('ti-camera f-s-16 text-muted')
            ->assertSee('Ver direcciones (sin GPS)')
            ->assertSeeHtml('ti-map-pin-off f-s-16');
    }

    /** Un pago migrado con coordenadas propias conserva el enlace del legacy ("Lugar del pago"). */
    public function test_el_pago_con_coordenadas_propias_conserva_su_enlace(): void
    {
        $this->usuario();
        $this->cobro(latitudPago: '-12.0500');

        $rows = Livewire::test(Payments::class)->viewData('rows');

        $this->assertSame('https://maps.google.com/?q=-12.0500000,-77.0428000', $rows[0]['direcciones']['pago']);
    }

    /** Dos fotos en el mismo cobro: las dos van al visor, en orden. */
    public function test_varias_fotos_del_mismo_cobro_van_juntas_al_visor(): void
    {
        $this->usuario();
        ['lote' => $lote] = $this->cobro();
        $e1 = $this->voucher($lote, 'a.jpg');
        $e2 = $this->voucher($lote, 'b.jpg', conThumb: false);

        $c = Livewire::test(Payments::class);
        $v = $c->viewData('rows')[0]['vouchers'];

        $this->assertSame(["/storage/expenses/{$e1}/a.jpg", "/storage/expenses/{$e2}/b.jpg"], array_column($v, 'url'));
        $this->assertSame("/storage/expenses/{$e2}/b.jpg", $v[1]['thumb'], 'sin miniatura se usa la foto');
        $c->assertSee('Ver voucher (2)');
    }

    /** El voucher de OTRO día del mismo crédito no se cuelga de este cobro. */
    public function test_el_voucher_de_otro_dia_no_se_mezcla(): void
    {
        $this->usuario();
        ['credit' => $credit] = $this->cobro();
        $loteAyer = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 333.33, 'date' => '2026-10-21', 'time' => '08:55:42',
            'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->voucher($loteAyer, 'ayer.jpg');

        $this->assertSame([], Livewire::test(Payments::class)->viewData('rows')[0]['vouchers']);
    }

    /** Sin el permiso de los niveles altos no hay columna Map, pero el voucher sí se ve. */
    public function test_sin_permiso_no_hay_map_pero_si_voucher(): void
    {
        $this->usuario(admin: false);
        ['client' => $client, 'lote' => $lote] = $this->cobro(['direccion' => 'Jr. Los Pinos 123']);
        ClientUbicacion::create(['client_id' => $client->id, 'tipo' => 'casa', 'nombre' => 'Casa', 'latitud' => '-12.0464', 'longitud' => '-77.0428']);
        $this->voucher($lote);

        Livewire::test(Payments::class)
            ->assertDontSee('Ver direcciones')
            ->assertSee('Ver voucher');
    }
}
