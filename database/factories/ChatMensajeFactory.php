<?php

namespace Database\Factories;

use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMensaje>
 */
class ChatMensajeFactory extends Factory
{
    protected $model = ChatMensaje::class;

    public function definition(): array
    {
        return [
            'conversacion_id' => ChatConversacion::factory(),
            'rol' => ChatMensaje::ROL_USUARIO,
            'contenido' => fake()->sentence(8),
        ];
    }

    /** Respuesta del asistente con tokens y costo ya calculados. */
    public function asistente(): static
    {
        return $this->state(fn () => [
            'rol' => ChatMensaje::ROL_ASISTENTE,
            'contenido' => 'Respuesta de prueba.',
            'modelo' => 'claude-sonnet-5-5',
            'tokens_entrada' => 1000,
            'tokens_salida' => 200,
            'costo_usd' => 0.004,
            'duracion_ms' => 1200,
        ]);
    }
}
