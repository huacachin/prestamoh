<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Quita el resaltado de la previa del contrato (16/10/2026: ContratoVm::v()
     * envuelve cada dato variable en <span class="variable">) para asertar
     * sobre el texto corrido.
     */
    protected function sinResaltado(string $html): string
    {
        return (string) preg_replace('#<span class="variable">(.*?)</span>#su', '$1', $html);
    }
}
