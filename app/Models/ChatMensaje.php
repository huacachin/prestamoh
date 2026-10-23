<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Mensaje del chat de consulta (23/10/2026): lo que preguntó el usuario o lo
 * que respondió el asistente, con las herramientas de lectura que corrió,
 * sus tokens (entrada, salida, caché) y el costo en USD de esa respuesta.
 */
class ChatMensaje extends Model
{
    use HasFactory;

    public const ROL_USUARIO = 'usuario';

    public const ROL_ASISTENTE = 'asistente';

    protected $table = 'chat_mensajes';

    protected $fillable = [
        'conversacion_id', 'rol', 'contenido', 'herramientas', 'modelo',
        'tokens_entrada', 'tokens_salida', 'tokens_cache_lectura', 'tokens_cache_escritura',
        'costo_usd', 'duracion_ms', 'error',
    ];

    protected $casts = ['herramientas' => 'array', 'costo_usd' => 'decimal:6'];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ChatConversacion::class, 'conversacion_id');
    }

    public function esAsistente(): bool
    {
        return $this->rol === self::ROL_ASISTENTE;
    }

    /** Total de tokens que se cobraron por esta respuesta (entrada + caché + salida). */
    public function tokens(): int
    {
        return (int) $this->tokens_entrada + (int) $this->tokens_cache_lectura + (int) $this->tokens_cache_escritura + (int) $this->tokens_salida;
    }

    /** Nombres de las herramientas que corrió el modelo para esta respuesta. */
    public function nombresDeHerramientas(): array
    {
        return array_values(array_unique(array_column($this->herramientas ?? [], 'nombre')));
    }

    /**
     * El texto del asistente viene en Markdown (negritas, listas, enlaces a
     * fichas y recibos); se convierte a HTML seguro y los enlaces abren aparte.
     */
    public function contenidoHtml(): string
    {
        $html = (string) Str::markdown($this->contenido, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);
        // Sin imágenes: un ![x](url) remoto haría que el navegador de quien lee avise a ese host.
        $html = (string) preg_replace('/<img\b[^>]*>/i', '', $html);

        return str_replace('<a href', '<a target="_blank" rel="noopener" href', $html);
    }
}
