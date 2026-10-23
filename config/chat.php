<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat de consulta (23/10/2026, pedido de Antony)
    |--------------------------------------------------------------------------
    |
    | Botón flotante + panel donde el usuario pregunta en lenguaje natural por
    | un cliente o un crédito y Claude responde usando HERRAMIENTAS DE SOLO
    | LECTURA escritas en PHP (App\Services\Chat\Herramientas). El modelo
    | nunca escribe SQL ni toca la base: solo pide una de esas consultas y
    | redacta con lo que devuelven. Sin CHAT_API_KEY el panel no se muestra.
    |
    | Prueba con los 200 USD de crédito que le regalaron a Antony: cada
    | respuesta guarda sus tokens y su costo (USD, según 'precios') y el panel
    | muestra el gasto del mes. 'conexion' es la conexión MySQL por la que
    | corren las herramientas: en producción 'lectura' (usuario con SOLO
    | SELECT, ver config/database.php); los tests usan 'mysql' (phpunit.xml)
    | porque una segunda conexión no ve la transacción del test.
    |
    */

    'habilitado' => filled(env('CHAT_API_KEY')),
    'api_key' => env('CHAT_API_KEY'),
    // Solo con una clave de usuario (sk-ant-usr-…) que la API exija atar a un workspace (wrkspc_…).
    'workspace' => env('CHAT_WORKSPACE_ID'),
    'modelo' => env('CHAT_MODELO', 'claude-sonnet-5-5'),
    'conexion' => env('CHAT_CONEXION', 'lectura'),

    // Límites: vueltas de consulta por pregunta, tokens de salida (incluye el
    // razonamiento del modelo, por eso es amplio), preguntas por día (todos
    // los usuarios), mensajes previos que viajan como contexto, segundos por
    // llamada a la API (×2 con el reintento) y segundos por pregunta completa.
    'max_herramientas' => 8,
    'max_tokens_respuesta' => 4000,
    'tope_preguntas_dia' => (int) env('CHAT_TOPE_PREGUNTAS_DIA', 300),
    'historial' => 12,
    'timeout' => 60,
    'presupuesto_segundos' => 120,
    // Esfuerzo de razonamiento (low | medium | high); vacío = el que trae el modelo.
    'esfuerzo' => env('CHAT_ESFUERZO'),

    // USD por millón de tokens [entrada, salida]. La caché del prompt se cobra
    // al 10 % (lectura) y al 125 % (escritura) de la entrada. Referencia de
    // septiembre de 2026; la tarifa vigente está en console.anthropic.com.
    'precios' => [
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-sonnet-5' => [2.00, 10.00],
        'claude-haiku-4-5-20251001' => [1.00, 5.00],
        'claude-opus-5-5' => [4.00, 20.00],
    ],

];
