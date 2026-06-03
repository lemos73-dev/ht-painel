<?php
/**
 * Cron de perguntas Mercado Livre → WhatsApp (CallMeBot)
 * Executar via cPanel Cron Jobs a cada 10 minutos:
 *   php /home1/vshosp73/public_html/painel/questions_cron.php
 */

// Bloqueia acesso via navegador
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/config.php';

$logFile   = dirname(dirname(__DIR__)) . '/ml_questions_log.txt';
$tokenFile = dirname(dirname(__DIR__)) . '/ml_tokens.php';
$lastFile  = dirname(dirname(__DIR__)) . '/ml_last_question.txt';

function logMsg($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

// Verifica se o token está salvo (usuário precisa ter feito login no painel ao menos uma vez)
if (!file_exists($tokenFile)) {
    logMsg('ERRO: Token nao encontrado. Acesse o painel e faca login primeiro.');
    exit(1);
}
require_once $tokenFile;

// ── 1. Renova o access_token usando o refresh_token ──────────────────────────
$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'refresh_token',
        'client_id'     => ML_CLIENT_ID,
        'client_secret' => ML_CLIENT_SECRET,
        'refresh_token' => ML_REFRESH_TOKEN_STORED,
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
]);
$tokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($tokenRes['access_token'])) {
    logMsg('ERRO ao renovar token: ' . json_encode($tokenRes));
    exit(1);
}

$accessToken = $tokenRes['access_token'];

// Salva novo refresh_token se vier atualizado
if (!empty($tokenRes['refresh_token'])) {
    $rt  = addslashes($tokenRes['refresh_token']);
    $uid = addslashes((string)($tokenRes['user_id'] ?? ML_USER_ID_STORED));
    $ts  = date('Y-m-d H:i:s');
    file_put_contents($tokenFile,
        "<?php\ndefine('ML_REFRESH_TOKEN_STORED','$rt');\ndefine('ML_USER_ID_STORED','$uid');\ndefine('ML_TOKEN_SAVED_AT','$ts');\n"
    );
}

// ── 2. Busca perguntas não respondidas ────────────────────────────────────────
$ch = curl_init('https://api.mercadolibre.com/my/received_questions/search?status=UNANSWERED&sort_fields=date_created&sort_orders=DESC&limit=10');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
]);
$qRes = json_decode(curl_exec($ch), true);
curl_close($ch);

$questions = $qRes['questions'] ?? [];

if (empty($questions)) {
    logMsg('Sem perguntas nao respondidas.');
    exit(0);
}

// ── 3. Filtra apenas as novas (depois do último ID registrado) ────────────────
$lastId = file_exists($lastFile) ? (int)trim(file_get_contents($lastFile)) : 0;
$newQs  = array_filter($questions, fn($q) => (int)$q['id'] > $lastId);

if (empty($newQs)) {
    logMsg('Nenhuma pergunta nova (ultimo ID: ' . $lastId . ').');
    exit(0);
}

// Salva o ID mais recente
$maxId = max(array_column($newQs, 'id'));
file_put_contents($lastFile, $maxId);
logMsg(count($newQs) . ' pergunta(s) nova(s) encontrada(s). Ultimo ID: ' . $maxId);

// ── 4. Busca título dos itens (batch) ─────────────────────────────────────────
$itemIds = array_unique(array_column($newQs, 'item_id'));
$itemTitles = [];
if (!empty($itemIds)) {
    $ch = curl_init('https://api.mercadolibre.com/items?ids=' . implode(',', $itemIds) . '&attributes=id,title');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    $itemRes = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (is_array($itemRes)) {
        foreach ($itemRes as $it) {
            $b = $it['body'] ?? $it;
            if (!empty($b['id'])) $itemTitles[$b['id']] = $b['title'] ?? $b['id'];
        }
    }
}

// ── 5. Envia WhatsApp via CallMeBot ──────────────────────────────────────────
$phone  = CALLMEBOT_PHONE;
$apikey = CALLMEBOT_APIKEY;

foreach (array_reverse(array_values($newQs)) as $q) {
    $titulo = $itemTitles[$q['item_id'] ?? ''] ?? ($q['item_id'] ?? 'Produto');
    $buyer  = $q['from']['nickname'] ?? 'Comprador';
    $texto  = $q['text'] ?? '';

    $msg  = "❓ *Nova pergunta ML*\n";
    $msg .= "📦 " . mb_strimwidth($titulo, 0, 60, '...') . "\n";
    $msg .= "💬 " . mb_strimwidth($texto, 0, 200, '...') . "\n";
    $msg .= "👤 " . $buyer . "\n";
    $msg .= "🔗 ml.me/ot/perguntas";

    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone'  => $phone,
        'text'   => $msg,
        'apikey' => $apikey,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $cbRes = curl_exec($ch);
    curl_close($ch);

    logMsg('WhatsApp enviado para pergunta #' . $q['id'] . ': ' . substr($cbRes, 0, 80));
    sleep(2); // respeita limite do CallMeBot
}

logMsg('Concluido.');
