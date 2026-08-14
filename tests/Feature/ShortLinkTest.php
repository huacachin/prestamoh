<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acortador propio para los links de recibo que van por WhatsApp:
 * /s/{code} → URL firmada. Idempotente, solo host propio, con contador.
 */
class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // En tests el root de las URLs es localhost; se alinea app.url para
        // que el guard de host propio funcione igual que en producción.
        config(['app.url' => 'http://localhost']);
    }

    public function test_acorta_redirige_y_cuenta(): void
    {
        $destino = 'http://localhost/recibo/120106?signature=abc123';

        $corto = ShortLink::para($destino);

        $this->assertMatchesRegularExpression('#/s/[A-Za-z0-9]{6}$#', $corto);

        $path = parse_url($corto, PHP_URL_PATH);
        $this->get($path)->assertRedirect($destino);
        $this->get($path);

        $this->assertSame(2, (int) ShortLink::first()->hits);
    }

    public function test_es_idempotente_por_destino(): void
    {
        $destino = 'http://localhost/recibo/120106?signature=abc123';

        $this->assertSame(ShortLink::para($destino), ShortLink::para($destino));
        $this->assertSame(1, ShortLink::count());

        // Otro destino → otro código.
        $otro = ShortLink::para('http://localhost/recibo/999?signature=zzz');
        $this->assertNotSame(ShortLink::para($destino), $otro);
        $this->assertSame(2, ShortLink::count());
    }

    public function test_no_acorta_hosts_ajenos(): void
    {
        // Acortar destinos ajenos convertiría /s/ en un open-redirect con
        // nuestro dominio de por medio: se devuelven tal cual.
        $ajena = 'https://malicioso.example.com/phishing';

        $this->assertSame($ajena, ShortLink::para($ajena));
        $this->assertSame(0, ShortLink::count());
    }

    public function test_codigo_desconocido_da_404(): void
    {
        $this->get('/s/NOEXIS')->assertNotFound();
    }
}
