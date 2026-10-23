<?php

namespace Tests\Feature;

use App\Livewire\Reports\Payments;
use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reporte de pagos (22/10, pedidos de Antony): cada cobro muestra la foto de
 * su depósito con la cámara de caja (el adjunto del egreso "Banco" colgado del
 * lote, en el visor movible), el recibo que genera el sistema (uno por lote,
 * en ventanitas flotantes, varias a la vez, con la fila en amarillo mientras
 * estén abiertas) y, en Map, SOLO el lugar desde donde se registró el pago
 * (payments.latitud/longitud, obligatorio al cobrar desde hoy).
 */
class ReportePagosVoucherReciboMapTest extends TestCase
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
    private function cobro(?string $latitudPago = null): array
    {
        $client = Client::create([
            'nombre' => 'Edwin', 'apellido_pat' => 'Leoncio', 'apellido_mat' => 'Escalera',
            'headquarter_id' => 1, 'status' => 'active',
        ]);
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

    public function test_la_fila_trae_la_foto_del_deposito_y_el_recibo_del_sistema(): void
    {
        $this->usuario();
        ['lote' => $lote] = $this->cobro();
        $egreso = $this->voucher($lote);

        $c = Livewire::test(Payments::class);
        $rows = $c->viewData('rows');

        $this->assertCount(1, $rows, 'un cobro = una fila');
        $this->assertSame('Leoncio Escalera Edwin', $rows[0]['cliente']);
        $this->assertArrayNotHasKey('direcciones', $rows[0], 'Map ya no trae las direcciones del cliente');

        $this->assertCount(1, $rows[0]['vouchers']);
        $this->assertSame("/storage/expenses/{$egreso}/voucher.jpg", $rows[0]['vouchers'][0]['url']);
        $this->assertSame("/storage/expenses/{$egreso}/thumbs/voucher.jpg", $rows[0]['vouchers'][0]['thumb']);
        $this->assertSame('Voucher 22/10/2026 08:55 · WhatsApp Image.jpeg', $rows[0]['vouchers'][0]['name']);

        // El recibo generado por el sistema ("Voucher"): uno por lote, con su link público firmado.
        $this->assertCount(1, $rows[0]['recibos']);
        $this->assertSame($lote, $rows[0]['recibos'][0]['lote']);
        $this->assertSame('08:55', $rows[0]['recibos'][0]['hora']);
        $this->assertSame(333.33, $rows[0]['recibos'][0]['monto']);
        $this->assertStringContainsString("/recibo/{$lote}?signature=", $rows[0]['recibos'][0]['url']);

        // La pantalla: cámara de caja (sin miniatura, con el contador) con las fotos inline, el recibo en
        // ventanitas (varias a la vez, fila amarilla), y Map con el mundo tachado (cobro sin ubicación).
        $c->assertSeeHtml('ti-camera f-s-16 text-info')
            ->assertSeeHtml('title="Ver foto del depósito (1)"')
            ->assertSeeHtml('badge bg-info') // el número al costado, como en ingresos/egresos (23/10)
            ->assertDontSeeHtml('<img src="/storage/expenses/')
            ->assertSee('Ver recibo (S/ 333.33)')
            ->assertSeeHtml('ti-receipt f-s-16')
            ->assertSeeHtml("verRecibo({$lote}, ")
            ->assertSeeHtml('class="recibos-ventanas"')
            ->assertSeeHtml("'fila-recibo': conRecibo(1)")
            ->assertDontSeeHtml('id="modal-recibo"')
            ->assertSeeHtml('ti-world-off f-s-16 text-muted')
            ->assertSee('Pago sin ubicación')
            ->assertDontSee('Ver direcciones')
            ->assertDontSeeHtml('ti-map-pin')
            ->assertSeeHtml('class="huac-lb"');
    }

    public function test_sin_voucher_la_camara_va_tachada_y_el_recibo_sigue(): void
    {
        $this->usuario();
        $this->cobro();

        $c = Livewire::test(Payments::class);

        $this->assertSame([], $c->viewData('rows')[0]['vouchers']);
        $c->assertDontSee('Ver foto del depósito')
            ->assertSeeHtml('ti-camera-off f-s-16 text-muted') // sin foto: cámara tachada en gris
            ->assertSee('Ver recibo (S/ 333.33)');
    }

    /** Map: el lugar desde donde se registró el pago (coordenadas del cobrador), como en el legacy. */
    public function test_map_enlaza_el_lugar_del_pago(): void
    {
        $this->usuario();
        $this->cobro(latitudPago: '-12.0500');

        Livewire::test(Payments::class)
            ->assertSeeHtml('href="https://maps.google.com/?q=-12.0500000,-77.0428000"')
            ->assertSee('Lugar del pago (ubicación de quien cobró)')
            ->assertSeeHtml('ti-world f-s-16 text-primary')
            ->assertDontSeeHtml('ti-world-off');
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
        $c->assertSee('Ver foto del depósito (2)');
    }

    /** El voucher y el recibo de OTRO día del mismo crédito no se cuelgan de este cobro. */
    public function test_el_voucher_y_el_recibo_de_otro_dia_no_se_mezclan(): void
    {
        $this->usuario();
        ['credit' => $credit] = $this->cobro();
        $loteAyer = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 333.33, 'date' => '2026-10-21', 'time' => '08:55:42',
            'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->voucher($loteAyer, 'ayer.jpg');

        $rows = Livewire::test(Payments::class)->viewData('rows');
        $this->assertSame([], $rows[0]['vouchers']);
        $this->assertNotSame($loteAyer, $rows[0]['recibos'][0]['lote'] ?? null, 'el recibo de ayer tampoco');
        $this->assertCount(1, $rows[0]['recibos'], 'solo el lote de hoy');
    }

    /** Dos cobros al mismo crédito el mismo día: dos recibos en la fila, cada uno con su hora. */
    public function test_dos_lotes_del_mismo_dia_dan_dos_recibos_con_su_hora(): void
    {
        $this->usuario();
        ['credit' => $credit] = $this->cobro();
        $segundo = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 50.00, 'date' => self::HOY, 'time' => '16:20:00',
            'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $c = Livewire::test(Payments::class);
        $recibos = $c->viewData('rows')[0]['recibos'];

        $this->assertSame(['08:55', '16:20'], array_column($recibos, 'hora'));
        $this->assertSame($segundo, $recibos[1]['lote']);
        $c->assertSee('Ver recibo de las 08:55 (S/ 333.33)')
            ->assertSee('Ver recibo de las 16:20 (S/ 50.00)');
    }

    /** Sin el permiso de los niveles altos no hay columna Map, pero la foto y el recibo sí se ven. */
    public function test_sin_permiso_no_hay_map_pero_si_foto_y_recibo(): void
    {
        $this->usuario(admin: false);
        ['lote' => $lote] = $this->cobro(latitudPago: '-12.0500');
        $this->voucher($lote);

        Livewire::test(Payments::class)
            ->assertDontSeeHtml('ti-world')
            ->assertDontSee('Lugar del pago')
            ->assertSee('Ver foto del depósito')
            ->assertSee('Ver recibo');
    }
}
