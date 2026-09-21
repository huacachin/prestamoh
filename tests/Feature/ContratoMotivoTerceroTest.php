<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El motivo del depósito a tercero es el texto fijo de la maestra a.1.1 y no
 * se pide en el modal (Antony, 21/09: "ese dato no es modificable"). Hasta
 * entonces había un campo editable que dejaba escribir cualquier cosa en una
 * cláusula que el área firma tal cual.
 */
class ContratoMotivoTerceroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_modal_del_contrato_no_pide_el_motivo(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'motivo-t']));
        $client = Client::create([
            'expediente' => '8200', 'nombre' => 'Cliente', 'apellido_pat' => 'Tercero', 'apellido_mat' => 'Prueba',
            'tipo_documento' => 'DNI', 'documento' => '04049001', 'sexo' => 'M', 'status' => 'active',
        ]);

        $c = Livewire::test(Documentos::class, ['id' => $client->id]);

        $c->assertDontSee('Motivo del depósito');
        $this->assertArrayNotHasKey('motivo', $c->get('tercero'), 'el motivo ya no es un dato del formulario');
        $this->assertSame(
            'EL DEUDOR PRESENTA PROBLEMAS ADMINISTRATIVOS CON SUS CUENTAS PERSONALES',
            Documentos::MOTIVO_TERCERO,
            'el texto fijo de la maestra a.1.1 es lo que va al contrato'
        );
    }
}
