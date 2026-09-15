<?php

namespace Tests\Feature;

use App\Support\Documentos\CorrelativoAnexo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correlativo del Anexo 1 (15/09). El área numera sus anexos "2026-NNN" y esa
 * es la numeración que citan en sus registros y ante la notaría — no el id
 * interno del crédito, que es lo que el anexo mostraba antes.
 *
 * Iban por el 229, así que el primero que emita el sistema es el 2026-230: si
 * arrancara en 1 chocaría con documentos ya firmados.
 */
class CorrelativoAnexoLegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_continua_donde_quedo_el_area(): void
    {
        // La migración sembró el 229 como último usado.
        $this->assertSame(229, (int) DB::table('correlativos')->where('tipo', 'AnexoLegal:2026')->value('correl'));
        $this->assertSame('2026-230', CorrelativoAnexo::proximo(2026));
        $this->assertSame('2026-230', CorrelativoAnexo::siguiente(2026));
        $this->assertSame('2026-231', CorrelativoAnexo::siguiente(2026));
    }

    /** proximo() es para la vista previa: mirar no puede quemar un número. */
    public function test_proximo_no_consume(): void
    {
        $antes = CorrelativoAnexo::proximo(2026);

        CorrelativoAnexo::proximo(2026);
        CorrelativoAnexo::proximo(2026);

        $this->assertSame($antes, CorrelativoAnexo::proximo(2026));
    }

    /** La serie es por año: en 2027 vuelve a empezar, sin tocar la de 2026. */
    public function test_la_serie_se_reinicia_cada_anio(): void
    {
        CorrelativoAnexo::siguiente(2026);

        $this->assertSame('2027-1', CorrelativoAnexo::siguiente(2027));
        $this->assertSame('2027-2', CorrelativoAnexo::siguiente(2027));
        // Y la de 2026 siguió su propia cuenta.
        $this->assertSame('2026-231', CorrelativoAnexo::siguiente(2026));
    }

    /** Volver a correr la siembra no debe reiniciar la cuenta. */
    public function test_la_siembra_es_idempotente(): void
    {
        CorrelativoAnexo::siguiente(2026);   // 230
        CorrelativoAnexo::siguiente(2026);   // 231

        $this->artisan('migrate', ['--force' => true]);

        $this->assertSame('2026-232', CorrelativoAnexo::proximo(2026));
    }
}
