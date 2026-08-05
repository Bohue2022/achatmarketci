<?php

/*
| Origines autorisées (CORS) pour l'API.
| En production : CORS_ALLOWED_ORIGINS=https://mon-domaine.vercel.app,...
| En local, les origines du serveur de dev sont autorisées par défaut.
*/

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:3000,http://localhost:3000')
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
