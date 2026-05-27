<?php
require_once __DIR__ . '/config.php';

// Apenas HTTPS
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https') {
    http_response_code(403);
    exit(json_encode(['error' => 'HTTPS obrigatorio']));
}

// Valida origem da requisicao
$allowed = ML_ALLOWED_DOMAIN;
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host    = $_SERVER['HTTP_HOST'] ?? '';
if (!str_contains($referer, $allowed) && !str_contains($host, $allowed)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Origem nao permitida']));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Token obrigatorio
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (empty($token)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Token ausente']));
}

// Valida e sanitiza o path da API
$path = $_GET['path'] ?? '/users/me';
if (!preg_match('#^/[a-zA-Z0-9/_\-?=&%.]+$#', $path)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Path invalido']));
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = file_get_contents('php://input');

$ch = curl_init('https://api.mercadolibre.com' . $path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
]);

if ($method !== 'GET' && !empty($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro de conexao com Mercado Livre']));
}

http_response_code($httpCode);
echo $response;
