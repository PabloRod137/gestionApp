<?php

/**
 * "Razonar con IA": modelo LOCAL (Ollama). Apagado por defecto. Solo bajo petición, limitado al cliente del
 * expediente, y nunca escribe ni envía nada: propone y una persona confirma.
 *
 * Ollama debe ser alcanzable desde el servidor del panel (el mismo equipo o la red privada del despacho).
 */
return [
    'enabled' => (bool) env('IA_ENABLED', false),

    'ollama' => [
        'url' => env('IA_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('IA_OLLAMA_MODEL', 'qwen3:4b-instruct-2507-q4_K_M'),
        'timeout' => (int) env('IA_OLLAMA_TIMEOUT', 75),
    ],

    // Carpeta raíz de los documentos del despacho sincronizada en el equipo donde corre el panel.
    // Si no existe, el modelo trabaja solo con los datos del expediente y del cliente.
    'carpetas_raiz' => env('IA_CARPETAS_RAIZ'),
];
