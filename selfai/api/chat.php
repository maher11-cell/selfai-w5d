<?php
/**
 * SelfAI — Chat endpoint
 * Calls DeepSeek V4 (deepseek-chat) primary, falls back to free providers
 * (OpenRouter / Groq / Gemini / Hugging Face) when DEEPSEEK_API_KEY is missing.
 */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();
$user = selfai_require_user();
$body = selfai_body();

$cloneId = (string)($body['clone_id'] ?? '');
$message = trim((string)($body['message'] ?? ''));
if ($cloneId === '' || $message === '') selfai_json(['error' => 'clone_id_and_message_required'], 400);

$clone = selfai_clone($cloneId);
if (!$clone) selfai_json(['error' => 'unknown_clone'], 404);

$cfg = selfai_config();
$udb = selfai_user_db((int)$user['id']);

// Pull last N turns for context
$ctxStmt = $udb->prepare("SELECT role, content FROM conversations WHERE clone_id = ? ORDER BY id DESC LIMIT ?");
$ctxStmt->bindValue(1, $cloneId, PDO::PARAM_STR);
$ctxStmt->bindValue(2, (int)($cfg['max_context_messages'] ?? 10), PDO::PARAM_INT);
$ctxStmt->execute();
$context = array_reverse($ctxStmt->fetchAll(PDO::FETCH_ASSOC));

// Build message stack
$messages = [['role' => 'system', 'content' => $clone['system_prompt']]];
foreach ($context as $row) $messages[] = ['role' => $row['role'], 'content' => $row['content']];
$messages[] = ['role' => 'user', 'content' => $message];

/** Call an OpenAI-compatible chat endpoint */
function selfai_call_provider(string $endpoint, string $apiKey, string $model, array $messages, float $temp): array {
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temp,
        'max_tokens' => 1024,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => "HTTP $code $err", 'raw' => $resp];
    }
    $j = json_decode($resp, true);
    $text = $j['choices'][0]['message']['content'] ?? '';
    $tokens = $j['usage']['total_tokens'] ?? 0;
    return ['ok' => true, 'text' => $text, 'tokens' => (int)$tokens, 'raw' => $resp];
}

$temp = (float)($cfg['temperature'] ?? 0.7);
$providersTried = [];

// 1) DeepSeek V4 (primary)
$deepKey = selfai_env('DEEPSEEK_API_KEY');
$result = null;
if ($deepKey !== '') {
    $providersTried[] = 'deepseek';
    $result = selfai_call_provider(
        $cfg['deepseek_endpoint'] ?? 'https://api.deepseek.com/v1/chat/completions',
        $deepKey,
        $cfg['deepseek_v4_model'] ?? 'deepseek-chat',
        $messages,
        $temp
    );
    if ($result['ok']) {
        $result['provider'] = 'deepseek';
    }
}

// 2) Free fallbacks
if (!$result || !$result['ok']) {
    $fallbacks = [
        ['env' => 'OPENROUTER_API_KEY', 'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',                  'model' => 'meta-llama/llama-3.1-8b-instruct:free',         'name' => 'openrouter'],
        ['env' => 'GROQ_API_KEY',        'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',               'model' => 'llama-3.3-70b-versatile',                       'name' => 'groq'],
        ['env' => 'GEMINI_API_KEY',      'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'model' => 'gemini-2.0-flash-exp',           'name' => 'gemini'],
        ['env' => 'HF_TOKEN',            'endpoint' => 'https://router.huggingface.co/v1/chat/completions',             'model' => 'meta-llama/Llama-3.2-3B-Instruct',              'name' => 'huggingface'],
    ];
    foreach ($fallbacks as $fb) {
        $k = selfai_env($fb['env']);
        if ($k === '') continue;
        $providersTried[] = $fb['name'];
        $try = selfai_call_provider($fb['endpoint'], $k, $fb['model'], $messages, $temp);
        if ($try['ok']) {
            $try['provider'] = $fb['name'];
            $result = $try;
            break;
        }
    }
}

// 3) Offline echo (no keys configured) — produces a structured pedagogical fallback
if (!$result || !$result['ok']) {
    $providersTried[] = 'offline_fallback';
    $offline = "[" . $clone['code'] . " · offline preview mode]\n\n" .
        "You said: " . $message . "\n\n" .
        "No live AI key configured. To enable real conversations, set DEEPSEEK_API_KEY (or any free fallback: OPENROUTER_API_KEY / GROQ_API_KEY / GEMINI_API_KEY / HF_TOKEN) in selfai/api/.env and reload.\n\n" .
        "Meanwhile, here is what " . $clone['code'] . " would normally do for this turn:\n" .
        "- Honor the verse " . $clone['ayah'] . "\n" .
        "- Run its 3-step audit (see /selfai docs)\n" .
        "- End with: '" . $clone['currency'] . " earned this turn: 1'";
    $result = ['ok' => true, 'text' => $offline, 'tokens' => 0, 'provider' => 'offline_fallback', 'raw' => ''];
}

$aiText   = $result['text'];
$tokens   = (int)($result['tokens'] ?? 0);
$provider = $result['provider'];

// Store user + assistant turns
$ins = $udb->prepare("INSERT INTO conversations (clone_id, clone_name, role, content, tokens_used) VALUES (?, ?, ?, ?, ?)");
$ins->execute([$cloneId, $clone['name'], 'user', $message, 0]);
$userTurnId = (int)$udb->lastInsertId();
$ins->execute([$cloneId, $clone['name'], 'assistant', $aiText, $tokens]);
$aiTurnId = (int)$udb->lastInsertId();

// Log API call
$udb->prepare("INSERT INTO deepseek_apis (endpoint, clone_id, request_payload, response, tokens_used, provider) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([
        $cfg['deepseek_endpoint'] ?? 'fallback',
        $cloneId,
        substr(json_encode(['messages' => $messages]), 0, 8000),
        substr($result['raw'] ?? '', 0, 8000),
        $tokens,
        $provider,
    ]);

// Award 2030B currency: parse "<CURRENCY> earned this turn: N" pattern; otherwise +1
$earned = 1;
$cur = $clone['currency'];
if (preg_match('/' . preg_quote($cur, '/') . '[^0-9]{0,30}(\d+)/u', $aiText, $m)) {
    $earned = max(1, min(5, (int)$m[1]));
}
$udb->prepare("INSERT INTO currency_ledger (currency, amount, source_clone, note) VALUES (?, ?, ?, ?)")
    ->execute([$cur, $earned, $cloneId, 'chat_turn:' . $aiTurnId]);

selfai_json([
    'ok' => true,
    'reply' => $aiText,
    'clone' => ['id' => $cloneId, 'code' => $clone['code'], 'name' => $clone['name']],
    'currency' => ['code' => $cur, 'earned' => $earned],
    'turn_ids' => ['user' => $userTurnId, 'assistant' => $aiTurnId],
    'provider' => $provider,
    'tokens' => $tokens,
    'providers_tried' => $providersTried,
]);
