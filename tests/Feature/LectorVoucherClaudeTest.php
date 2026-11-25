<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Services\Documentos\Ocr\LectorVoucherClaude;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * El lector real de vouchers, con la API simulada: no gasta llamadas. Comprueba lo que se le pide al modelo
 * y lo que el lector devuelve de su respuesta.
 */
class LectorVoucherClaudeTest extends TestCase
{
    /** PNG 1x1 válido: el lector deduce el tipo por el contenido. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** Lo que se le envió a la API en cada llamada. */
    private array $enviado = [];

    /**
     * Lector cuya API responde con $lectura como salida estructurada.
     *
     * @param  array<string, string>  $lectura
     */
    private function lectorQueResponde(array $lectura): LectorVoucherClaude
    {
        config(['services.anthropic.modelo' => 'modelo-de-prueba']);

        $pila = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_prueba', 'type' => 'message', 'role' => 'assistant', 'model' => 'modelo-de-prueba',
            'content' => [['type' => 'text', 'text' => json_encode($lectura, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ]))]));
        $pila->push(Middleware::history($this->enviado));

        return new LectorVoucherClaude(new Client(apiKey: 'sk-ant-prueba', requestOptions: ['transporter' => new Guzzle(['handler' => $pila])]));
    }

    private function voucher(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'voucher');
        file_put_contents($ruta, base64_decode(self::PNG_1X1));

        return $ruta;
    }

    /**
     * 24/11/2026 (voucher de Caja Huancayo a otra plaza, "Com(Fue_Pla) ******203.42"): la comisión que el
     * banco cobró por la operación se pide y se devuelve aparte del monto; la resta la hace el cuadre.
     */
    public function test_pide_y_devuelve_la_comision_del_banco_aparte_del_monto(): void
    {
        $ruta = $this->voucher();
        $lector = $this->lectorQueResponde([
            'transcripcion' => 'AHORRO; DEPOSITO REM. AG. CARHUAMAYO; DEPOSITO ***45,203.40; Com(Fue_Pla) ******203.42.',
            'monto' => '45,203.40', 'beneficiario' => 'CHUCO CAJAHUANCA, SAUL GAUDENCIO', 'dudas' => '',
            'banco' => 'Caja Huancayo', 'modalidad' => 'deposito', 'comision' => ' 203.42 ',
        ]);

        try {
            $leido = $lector->leer($ruta, '', '');
        } finally {
            unlink($ruta);
        }

        $this->assertSame(
            ['45,203.40', '203.42', 'caja_huancayo', 'deposito', 'modelo-de-prueba'],
            [$leido['monto'], $leido['comision'], $leido['banco'], $leido['modalidad'], $leido['modelo']]
        );
        $this->assertSame('AHORRO; DEPOSITO REM. AG. CARHUAMAYO; DEPOSITO ***45,203.40; Com(Fue_Pla) ******203.42', $leido['transcripcion']);

        $pedido = json_decode((string) $this->enviado[0]['request']->getBody(), true);
        $this->assertContains('comision', $pedido['output_config']['format']['schema']['required']);
        $instrucciones = collect($pedido['messages'][0]['content'])->firstWhere('type', 'text')['text'];
        $this->assertStringContainsString('"comision": la comisión que el banco cobró por ESTA operación', $instrucciones);
        $this->assertStringContainsString('No le restes comisiones', $instrucciones);
    }
}
