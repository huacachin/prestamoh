<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\User;
use App\Services\Documentos\GeneradorContrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El motivo del depósito a tercero es el texto fijo de las maestras y no
 * se pide en el modal (Antony, 21/09: "ese dato no es modificable"). Hasta
 * entonces había un campo editable que dejaba escribir cualquier cosa en una
 * cláusula que el área firma tal cual.
 *
 * Fijo no quiere decir igual para todos: las maestras lo concuerdan con
 * quien debe (a.1.1 "EL DEUDOR PRESENTA", a.2.1 "LA DEUDORA PRESENTA", a.3.1
 * "LOS DEUDORES PRESENTAN") y hasta el 19/11 el contrato de una deudora
 * decía "EL DEUDOR PRESENTA".
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
    }

    public function test_el_motivo_concuerda_con_quien_debe(): void
    {
        $resto = ' PROBLEMAS ADMINISTRATIVOS CON SUS CUENTAS PERSONALES';

        $this->assertSame('EL DEUDOR PRESENTA'.$resto, GeneradorContrato::motivoTercero(['M']), 'maestra a.1.1');
        $this->assertSame('LA DEUDORA PRESENTA'.$resto, GeneradorContrato::motivoTercero(['F']), 'maestra a.2.1');
        $this->assertSame('LOS DEUDORES PRESENTAN'.$resto, GeneradorContrato::motivoTercero(['F', 'M']), 'maestra a.3.1');
        $this->assertSame('LOS DEUDORES PRESENTAN'.$resto, GeneradorContrato::motivoTercero(['F', 'F']), 'el plural es siempre LOS DEUDORES');
    }
}
