<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz redirige al login (la app es de acceso autenticado).
     */
    public function test_the_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
