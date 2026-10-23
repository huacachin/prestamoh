<?php

use App\Services\Credits\EliminadorCredito;
use Illuminate\Database\Migrations\Migration;

/**
 * Correlativo de créditos (23/10/2026, Antony): el #29494 se eliminó cuando el
 * recuento todavía tomaba max(credits.id), que es el crédito migrado #87421, así
 * que el contador se quedó en 29494 y el siguiente crédito iba a salir 29495.
 * Con el recuento corregido se aplica una vez: baja al mayor código existente
 * que no pase del contador (29493) y el próximo crédito toma el 29494.
 *
 * Es idempotente y nunca sube el contador: si entretanto se creó otro crédito,
 * no cambia nada (el 29494 queda como hueco). En una base vacía no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        EliminadorCredito::recontarCorrelativo();
    }

    public function down(): void
    {
        // Sin vuelta atrás: el valor anterior del contador apuntaba a un código que ya no existe.
    }
};
