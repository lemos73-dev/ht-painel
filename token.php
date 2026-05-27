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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Metodo nao permitido']));
}

// Monta parametros — client_secret fica APENAS aqui no servidor
$params = [
    'grant_type'    => $_POST['grant_type']    ?? '',
    'client_id'     => $_POST['client_id']     ?? '',
    'client_secret' => ML_CLIENT_SECRET,
    'code'          => $_POST['code']          ?? '',
    'redirect_uri'  => $_POST['redirect_uri']  ?? '',
    'refresh_token' => $_POST['refresh_token'] ?? '',
];

// Remove campos vazios
$params = array_filter($params, fn($v) => $v !== '');

$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
]);

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
