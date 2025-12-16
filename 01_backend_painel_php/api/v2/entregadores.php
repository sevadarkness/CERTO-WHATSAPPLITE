<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Este endpoint ainda não foi implementado
http_response_code(501);
echo json_encode([
    'ok' => false,
    'error' => [
        'code' => 'not_implemented',
        'message' => 'Este endpoint ainda não foi implementado. Use a API v1 ou aguarde uma atualização.'
    ],
    'meta' => [
        'endpoint' => basename(__FILE__, '.php'),
        'version' => 'v2'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
