<?php

namespace Database\Factories;

use App\Models\ChatConversacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversacion>
 */
class ChatConversacionFactory extends Factory
{
    protected $model = ChatConversacion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => ['username' => fake()->unique()->userName()]),
            'titulo' => fake()->sentence(6),
            'modelo' => 'claude-sonnet-5-5',
            'preguntas' => 0,
            'tokens_entrada' => 0,
            'tokens_salida' => 0,
            'costo_usd' => 0,
            'ultima_actividad_at' => now(),
        ];
    }
}
