<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acortador de links propio (prestamoh.huacachin.pe/s/{code}).
 *
 * Los recibos públicos que se mandan por WhatsApp llevan firma criptográfica
 * (~130 caracteres de URL): dentro del mensaje se ven horribles y el cliente
 * desconfía. El acortador guarda el destino y entrega un código de 6
 * caracteres — sin servicios externos (bitly y cía.): el link nunca sale del
 * dominio propio y no depende de terceros.
 *
 * `hash` (sha256 del destino) deduplica: acortar dos veces la misma URL
 * devuelve el mismo código, así la tabla crece con los recibos, no con las
 * visitas a la pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->text('destino');
            $table->char('hash', 64)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
