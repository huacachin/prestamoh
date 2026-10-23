<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Conversación del chat de consulta (23/10/2026): un hilo de preguntas y
 * respuestas de un usuario, con el acumulado de tokens y costo en USD.
 * No lleva auditoría automática: cada pregunta se anota a mano con
 * Audit::log('Consultó …') desde el servicio.
 */
class ChatConversacion extends Model
{
    use HasFactory;

    protected $table = 'chat_conversaciones';

    protected $fillable = ['user_id', 'titulo', 'modelo', 'preguntas', 'tokens_entrada', 'tokens_salida', 'costo_usd', 'ultima_actividad_at'];

    protected $casts = ['ultima_actividad_at' => 'datetime', 'costo_usd' => 'decimal:6'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ChatMensaje::class, 'conversacion_id')->orderBy('id');
    }

    /** Suma al hilo la respuesta recién guardada y le pone título si es la primera pregunta. */
    public function registrarRespuesta(ChatMensaje $respuesta, string $pregunta): void
    {
        $this->forceFill([
            'titulo' => $this->titulo ?? Str::limit(trim($pregunta), 110),
            'modelo' => $respuesta->modelo ?? $this->modelo,
            'preguntas' => $this->preguntas + 1,
            'tokens_entrada' => $this->tokens_entrada + $respuesta->tokens_entrada + $respuesta->tokens_cache_lectura + $respuesta->tokens_cache_escritura,
            'tokens_salida' => $this->tokens_salida + $respuesta->tokens_salida,
            'costo_usd' => round((float) $this->costo_usd + (float) $respuesta->costo_usd, 6),
            'ultima_actividad_at' => now(),
        ])->save();
    }
}
