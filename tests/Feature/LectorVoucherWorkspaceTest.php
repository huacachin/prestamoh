<?php

namespace Tests\Feature;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Services\Documentos\Ocr\LectorVoucherClaude;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 16/10/2026: la clave nueva de Antony es de usuario (sk-ant-usr-…) y la API
 * rechaza las llamadas sin la cabecera anthropic-workspace-id ("This API key
 * is not scoped to a workspace"). Con ANTHROPIC_WORKSPACE_ID el lector la
 * manda; con una clave de workspace se deja vacío y no manda nada.
 */
class LectorVoucherWorkspaceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function cabecerasDelCliente(): array
    {
        $lector = new LectorVoucherClaude;
        $metodo = new ReflectionMethod($lector, 'cliente');
        $cliente = $metodo->invoke($lector);
        $this->assertInstanceOf(Client::class, $cliente);

        // Las opciones viven en una propiedad (propia o heredada) de tipo RequestOptions.
        for ($clase = new ReflectionClass($cliente); $clase; $clase = $clase->getParentClass()) {
            foreach ($clase->getProperties() as $prop) {
                $valor = $prop->getValue($cliente);
                if ($valor instanceof RequestOptions) {
                    return $valor->extraHeaders ?? [];
                }
            }
        }
        $this->fail('el cliente no expone sus RequestOptions');
    }

    public function test_con_workspace_configurado_manda_la_cabecera(): void
    {
        config(['services.anthropic.key' => 'sk-ant-usr-prueba', 'services.anthropic.workspace' => ' wrkspc_01ABC ']);
        $this->assertSame('wrkspc_01ABC', $this->cabecerasDelCliente()['anthropic-workspace-id'] ?? null);
    }

    public function test_sin_workspace_no_manda_nada(): void
    {
        config(['services.anthropic.key' => 'sk-ant-api03-prueba', 'services.anthropic.workspace' => null]);
        $this->assertArrayNotHasKey('anthropic-workspace-id', $this->cabecerasDelCliente());
    }
}
