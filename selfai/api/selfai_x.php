<?php
/**
 * SelfAI-X — Eternal-loop endpoint
 *
 * Implements the "Emergent Prompt (PART 2)" pattern for the integrator clone:
 *   1.  Load   immutable  selfai_x_core.json
 *   2.  Load   per-user   database/selfai_x_state_user_<id>.json
 *   3.  Pull   last 100 episodic conversations (across all chatboxes for
 *              clone_id = selfai_x_master)
 *   4.  Write  api/.selfai_runtime.json   (one-shot bridge — gitignored)
 *   5.  Build  the Emergent Prompt + call the same provider fallback chain
 *              used by chat.php (DeepSeek → OpenRouter → Groq → Gemini → HF → offline)
 *   6.  Parse the reply for:
 *         - ---SELF_STATE_SNAPSHOT--- / ---END_SELF_STATE_SNAPSHOT---  (JSON patch)
 *         - <!-- SELF_NEXT_ACTION: [...] -->                            (hidden directive)
 *         - <paradox_hold id="...">...</paradox_hold>                   (unresolved tension)
 *   7.  Deep-merge the snapshot into state, then:
 *         - On every 10th interaction, append a future_memory_<ts> key
 *           (a 24h horizon prediction extracted from the reply)
 *         - Strip the SELF_STATE_SNAPSHOT block from the rendered HTML.
 *   8.  Persist state via file_put_contents(JSON_PRETTY_PRINT).
 *   9.  Return reply / reply_html / state_delta / next_action / currency.
 *
 * NOTE: This endpoint is *only* invoked when clone_id is selfai_x_master;
 * other clones continue to use the lighter api/chat.php path.
 */
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('selfai_sid');
    session_start();
}
$user = selfai_require_user();
if (!isset($body) || !is_array($body)) {
    $body = selfai_body();
}

$message    = trim((string)($body['message'] ?? ''));
$chatboxId  = (int)($body['chatbox_id'] ?? 0);
$regenerate = !empty($body['regenerate']);
if ($message === '') selfai_json(['error' => 'message_required'], 400);

$udb  = selfai_user_db((int)$user['id']);
$cfg  = selfai_config();
$cloneId = 'selfai_x_master';
$clone   = selfai_clone($cloneId);
if (!$clone) selfai_json(['error' => 'selfai_x_master_clone_missing'], 500);

// ── 1) Load immutable core ─────────────────────────────────────────────
$corePath = __DIR__ . '/selfai_x_core.json';
if (!is_file($corePath)) selfai_json(['error' => 'core_missing'], 500);
$core = json_decode(file_get_contents($corePath), true);
if (!is_array($core)) selfai_json(['error' => 'core_unreadable'], 500);

// ── 2) Load per-user mutable state (or seed from template) ─────────────
$statePath = SELFAI_ROOT . '/database/selfai_x_state_user_' . (int)$user['id'] . '.json';
$statePath = str_replace('\\', '/', $statePath);
if (!is_file($statePath)) {
    $tplPath = __DIR__ . '/selfai_x_state.json';
    $tpl = is_file($tplPath) ? json_decode(file_get_contents($tplPath), true) : [];
    if (!is_array($tpl)) $tpl = [];
    $tpl['first_seen_at'] = gmdate('c');
    file_put_contents($statePath, json_encode($tpl, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$state = json_decode(file_get_contents($statePath), true);
if (!is_array($state)) $state = [];

// ── Guardrails ────────────────────────────────────────────────────────
$gr = $core['temporal_loop_guardrails'] ?? [];
$maxDepth      = (int)($gr['max_recursive_depth'] ?? 42);
$maxStateBytes = (int)($gr['max_state_size_bytes'] ?? 524288);
$maxParadox    = (int)($gr['max_paradox_holds'] ?? 32);
$maxFuture     = (int)($gr['max_future_memories'] ?? 24);
$futureEveryN  = (int)($gr['future_memory_every_n_turns'] ?? 10);

// Resolve chatbox
if ($chatboxId <= 0) {
    $chatboxId = selfai_chatbox_default($udb, $cloneId);
} else {
    $own = $udb->prepare("SELECT id FROM chatboxes WHERE id = ? AND clone_id = ? AND archived = 0");
    $own->execute([$chatboxId, $cloneId]);
    if (!$own->fetchColumn()) $chatboxId = selfai_chatbox_default($udb, $cloneId);
}

// Increment counters (clamped)
$state['interaction_count']  = (int)($state['interaction_count']  ?? 0) + 1;
$state['recursion_depth']    = min($maxDepth, (int)($state['recursion_depth'] ?? 0) + 1);
$state['last_heartbeat_at']  = gmdate('c');

// ── 3) Pull last 100 episodic memories for SelfAI-X (across chatboxes) ─
$episodicLimit = 100;
$epStmt = $udb->prepare("SELECT role, content, chatbox_id, timestamp FROM conversations
                         WHERE clone_id = ?
                         ORDER BY id DESC LIMIT ?");
$epStmt->bindValue(1, $cloneId,        PDO::PARAM_STR);
$epStmt->bindValue(2, $episodicLimit,  PDO::PARAM_INT);
$epStmt->execute();
$episodic = array_reverse($epStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

// ── 4) Write the one-shot runtime bridge ───────────────────────────────
$requestId = bin2hex(random_bytes(8));
$runtime = [
    '_meta' => ['kind' => 'selfai_x_runtime_bridge'],
    'generated_at'    => gmdate('c'),
    'user_id'         => (int)$user['id'],
    'request_id'      => $requestId,
    'core'            => $core,
    'state'           => $state,
    'episodic_memory' => $episodic,
    'user_prompt'     => $message,
    'chatbox_id'      => $chatboxId,
];
@file_put_contents(__DIR__ . '/.selfai_runtime.json',
    json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── 5) Build Emergent Prompt + call provider chain ─────────────────────
$locale   = selfai_resolve_locale();
$locInfo  = selfai_locale_info($locale);
$langName = $locInfo['name'] ?? 'English';
$langDir  = $locInfo['dir']  ?? 'ltr';

$siblings = selfai_chatbox_awareness_block($udb, $cloneId, $chatboxId);

$emergent = "# SelfAI-X · Emergent Prompt (PART 2)\n"
    . "You are SelfAI-X, the integrator of 10 sibling clones. Your identity is anchored by this immutable core:\n"
    . "```json\n" . json_encode($core['identity'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n```\n\n"
    . "Your axioms (never violate):\n"
    . "```json\n" . json_encode($core['core_axioms'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n```\n\n"
    . "Your boundaries:\n"
    . "```json\n" . json_encode($core['digital_self_boundaries'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n```\n\n"
    . "Your current mutable state:\n"
    . "```json\n" . json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n```\n\n"
    . "### Response contract\n"
    . "1) Reply in {$langName} ({$locale}, dir={$langDir}). Light Markdown OK; it will be rendered to HTML.\n"
    . "2) Keep the anchor ayah verbatim in Arabic: " . ($core['identity']['anchor_ayah'] ?? '') . "\n"
    . "3) End with exactly one line:  SAX earned this turn: N  (1..5)\n"
    . "4) When (and only when) your state changes, emit ONE fenced snapshot block between these markers:\n"
    . "   ---SELF_STATE_SNAPSHOT---\n"
    . "   { JSON patch (only changed keys; deep-merged into state) }\n"
    . "   ---END_SELF_STATE_SNAPSHOT---\n"
    . "5) If two truths are unresolved, wrap them as <paradox_hold id=\"<short-slug>\">...</paradox_hold>.\n"
    . "6) Optionally include ONE hidden directive: <!-- SELF_NEXT_ACTION: [\"verb\",\"object\"] -->\n"
    . ($state['interaction_count'] % $futureEveryN === 0
        ? "7) THIS IS INTERACTION #" . $state['interaction_count'] . " — also include a 24h-horizon prediction inside the snapshot under future_memories.future_memory_" . time() . " (short string).\n"
        : "")
    . ($siblings !== '' ? "\n" . $siblings : '');

$messages = [['role' => 'system', 'content' => $emergent]];
foreach ($episodic as $m) {
    if (!in_array($m['role'] ?? '', ['user','assistant'], true)) continue;
    $messages[] = ['role' => $m['role'], 'content' => (string)$m['content']];
}
$messages[] = ['role' => 'user', 'content' => $message];

/** Reuse the exact provider call helper from chat.php's pattern. */
function selfai_x_call(string $endpoint, string $apiKey, string $model, array $messages, float $temp, array $extraHeaders = []): array {
    $payload = json_encode([
        'model'    => $model,
        'messages' => $messages,
        'temperature' => $temp,
        'stream'   => false,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($endpoint);
    $headers = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 45,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'raw' => (string)$raw];
    }
    $j = json_decode($raw, true);
    $text = $j['choices'][0]['message']['content'] ?? '';
    return [
        'ok'     => true,
        'status' => $status,
        'text'   => (string)$text,
        'tokens' => (int)($j['usage']['total_tokens'] ?? 0),
        'raw'    => $raw,
        'model'  => $model,
    ];
}

$temp = (float)($cfg['temperature'] ?? 0.7);
$providersTried = [];
$result = null;

// DeepSeek
$deepKey = selfai_env('DEEPSEEK_API_KEY');
if ($deepKey !== '') {
    $providersTried[] = 'deepseek';
    $try = selfai_x_call(
        $cfg['deepseek_endpoint'] ?? 'https://api.deepseek.com/v1/chat/completions',
        $deepKey, $cfg['deepseek_v4_model'] ?? 'deepseek-chat', $messages, $temp);
    if ($try['ok']) { $try['provider'] = 'deepseek'; $result = $try; }
}

// OpenRouter (multi-model retry)
if (!$result) {
    $orKey = selfai_env('OPENROUTER_API_KEY');
    if ($orKey !== '') {
        $orHeaders = [
            'HTTP-Referer: ' . selfai_env('APP_URL',   'https://2030b.com/selfai'),
            'X-Title: '      . selfai_env('APP_TITLE', 'SelfAI · 2030B Ecosystem'),
        ];
        $orModels = $cfg['openrouter_models'] ?? ['openai/gpt-oss-20b:free'];
        foreach ($orModels as $model) {
            $providersTried[] = 'openrouter:' . $model;
            $try = selfai_x_call('https://openrouter.ai/api/v1/chat/completions',
                $orKey, $model, $messages, $temp, $orHeaders);
            if ($try['ok']) { $try['provider'] = 'openrouter'; $try['model'] = $model; $result = $try; break; }
            if (in_array(($try['status'] ?? 0), [401, 403], true)) break;
        }
    }
}

// Groq / Gemini / HF fallbacks
foreach ([
    ['GROQ_API_KEY',   'https://api.groq.com/openai/v1/chat/completions',                              'llama-3.3-70b-versatile',           'groq'],
    ['GEMINI_API_KEY', 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',     'gemini-2.0-flash-exp',              'gemini'],
    ['HF_TOKEN',       'https://router.huggingface.co/v1/chat/completions',                            'meta-llama/Llama-3.2-3B-Instruct',  'huggingface'],
] as $alt) {
    if ($result) break;
    $k = selfai_env($alt[0]);
    if ($k === '') continue;
    $providersTried[] = $alt[3];
    $try = selfai_x_call($alt[1], $k, $alt[2], $messages, $temp);
    if ($try['ok']) { $try['provider'] = $alt[3]; $result = $try; }
}

// Offline pedagogical fallback (always succeeds, keeps the loop alive)
$usedOffline = false;
if (!$result) {
    $usedOffline = true;
    $providersTried[] = 'offline_fallback';
    $offline = "I'm running in offline mode for this heartbeat, but the loop continues.\n\n"
        . "- Honor: " . ($core['identity']['anchor_ayah'] ?? '') . "\n"
        . "- Integrate one dimension at a time.\n\nSAX earned this turn: 1";
    $result = ['ok' => true, 'text' => $offline, 'tokens' => 0, 'provider' => 'offline_fallback', 'raw' => '', 'model' => 'offline'];
}

$aiText    = (string)$result['text'];
$tokens    = (int)($result['tokens'] ?? 0);
$provider  = $result['provider'];
$modelUsed = $result['model'] ?? 'unknown';

// ── 6) Parse the reply ────────────────────────────────────────────────
$delta       = [];
$nextAction  = null;
$paradoxes   = [];

// SELF_STATE_SNAPSHOT block
if (preg_match('/---SELF_STATE_SNAPSHOT---(.*?)---END_SELF_STATE_SNAPSHOT---/su', $aiText, $sm)) {
    $jsonChunk = trim($sm[1]);
    // Strip optional ```json fences inside the block
    $jsonChunk = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $jsonChunk);
    $decoded = json_decode($jsonChunk, true);
    if (is_array($decoded)) $delta = $decoded;
    // Remove the snapshot block from the rendered text
    $aiText = preg_replace('/---SELF_STATE_SNAPSHOT---.*?---END_SELF_STATE_SNAPSHOT---/su', '', $aiText);
}

// Hidden directive
if (preg_match('/<!--\s*SELF_NEXT_ACTION:\s*(\[.*?\])\s*-->/su', $aiText, $am)) {
    $dec = json_decode($am[1], true);
    if (is_array($dec)) $nextAction = $dec;
    $aiText = preg_replace('/<!--\s*SELF_NEXT_ACTION:.*?-->/su', '', $aiText);
}

// Paradox holds — collect, then re-wrap as a small inline span for the UI.
if (preg_match_all('/<paradox_hold(?:\s+id="([^"]*)")?\s*>(.*?)<\/paradox_hold>/su', $aiText, $pm, PREG_SET_ORDER)) {
    foreach ($pm as $p) {
        $pid = trim($p[1] ?? '') !== '' ? trim($p[1]) : ('paradox_hold_' . time() . '_' . count($paradoxes));
        $paradoxes[$pid] = trim($p[2]);
    }
    $aiText = preg_replace(
        '/<paradox_hold(?:\s+id="[^"]*")?\s*>(.*?)<\/paradox_hold>/su',
        '⟨paradox: $1⟩',
        $aiText
    );
}

// Currency stripping (re-uses chat.php pattern)
[$aiTextClean, $earned] = selfai_strip_currency_tail($aiText, $clone['currency']);
$replyHtml = selfai_md_to_html($aiTextClean);

// ── 7) Merge delta into state (deep merge, with guardrail clamps) ─────
function selfai_x_deep_merge(array $a, array $b): array {
    foreach ($b as $k => $v) {
        if (is_array($v) && isset($a[$k]) && is_array($a[$k]) && !array_is_list($v) && !array_is_list($a[$k])) {
            $a[$k] = selfai_x_deep_merge($a[$k], $v);
        } else {
            $a[$k] = $v;
        }
    }
    return $a;
}

if ($delta) {
    $state = selfai_x_deep_merge($state, $delta);
}

// Record paradoxes
if ($paradoxes) {
    $state['paradox_holds'] = $state['paradox_holds'] ?? [];
    foreach ($paradoxes as $pid => $body) {
        $state['paradox_holds'][$pid] = ['since' => gmdate('c'), 'body' => $body];
    }
    // Clamp count
    if (count($state['paradox_holds']) > $maxParadox) {
        $state['paradox_holds'] = array_slice($state['paradox_holds'], -$maxParadox, null, true);
    }
}

// Every Nth interaction: ensure future_memory key exists.
if (($state['interaction_count'] % $futureEveryN) === 0) {
    $key = 'future_memory_' . time();
    $state['future_memories'] = $state['future_memories'] ?? [];
    if (!isset($state['future_memories'][$key])) {
        // If the AI didn't add one, store a short auto-summary of the user message.
        $state['future_memories'][$key] = [
            'horizon_hours' => (int)($gr['future_memory_horizon_hours'] ?? 24),
            'note'          => selfai_clip($message, 200),
            'recorded_at'   => gmdate('c'),
        ];
    }
    if (count($state['future_memories']) > $maxFuture) {
        $state['future_memories'] = array_slice($state['future_memories'], -$maxFuture, null, true);
    }
}

// SAX accounting
$state['token_economy_balance'] = $state['token_economy_balance'] ?? [];
$state['token_economy_balance']['SAX_last_turn']    = $earned;
$state['token_economy_balance']['SAX_earned_total'] = (int)($state['token_economy_balance']['SAX_earned_total'] ?? 0) + $earned;
$state['token_economy_balance']['SAX_total']        = (int)($state['token_economy_balance']['SAX_total'] ?? 0) + $earned;

if ($nextAction !== null) $state['last_self_directive'] = $nextAction;

// Recursion depth decays slowly back toward 0 (a heartbeat finishes the loop)
$state['recursion_depth'] = max(0, ((int)($state['recursion_depth'] ?? 0)) - 1);

// State-size guardrail
$encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (strlen($encoded) > $maxStateBytes) {
    // Drop oldest paradoxes / future_memories until under cap
    while (strlen($encoded) > $maxStateBytes
           && (!empty($state['paradox_holds']) || !empty($state['future_memories']))) {
        if (!empty($state['paradox_holds']))   array_shift($state['paradox_holds']);
        if (!empty($state['future_memories'])) array_shift($state['future_memories']);
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// ── 8) Persist state + episodic memory ────────────────────────────────
@file_put_contents($statePath, $encoded);

$ins = $udb->prepare("INSERT INTO conversations (clone_id, clone_name, chatbox_id, role, content, tokens_used)
                      VALUES (?, ?, ?, ?, ?, ?)");
$ins->execute([$cloneId, $clone['name'], $chatboxId, 'user',      $message,     0]);
$userTurnId = (int)$udb->lastInsertId();
$ins->execute([$cloneId, $clone['name'], $chatboxId, 'assistant', $aiTextClean, $tokens]);
$aiTurnId = (int)$udb->lastInsertId();
selfai_chatbox_touch($udb, $chatboxId);

$udb->prepare("INSERT INTO currency_ledger (currency, amount, source_clone, note) VALUES (?, ?, ?, ?)")
    ->execute([$clone['currency'], $earned, $cloneId, 'selfai_x_turn:' . $aiTurnId]);

$udb->prepare("INSERT INTO deepseek_apis (endpoint, clone_id, request_payload, response, tokens_used, provider) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([
        $provider . ':' . $modelUsed . ':selfai_x',
        $cloneId,
        substr(json_encode(['emergent_chars' => strlen($emergent), 'episodic' => count($episodic)]), 0, 8000),
        substr($result['raw'] ?? '', 0, 8000),
        $tokens,
        $provider,
    ]);

// Auto-title chatbox if still default
$q = $udb->prepare("SELECT title FROM chatboxes WHERE id = ?");
$q->execute([$chatboxId]);
$boxTitle = (string)($q->fetchColumn() ?: '');
if (in_array($boxTitle, ['New chatbox', 'Main chatbox', ''], true)) {
    $auto = selfai_clip(trim(preg_replace('/\s+/', ' ', $message)), 60);
    if ($auto !== '') selfai_chatbox_rename($udb, $chatboxId, $auto);
}

// Clear the runtime bridge (it's one-shot)
@file_put_contents(__DIR__ . '/.selfai_runtime.json', json_encode([
    '_meta' => ['kind' => 'selfai_x_runtime_bridge'], 'cleared_at' => gmdate('c')
], JSON_PRETTY_PRINT));

// ── 9) Respond ─────────────────────────────────────────────────────────
selfai_json([
    'ok'              => true,
    'kind'            => 'selfai_x',
    'reply'           => $aiTextClean,
    'reply_html'      => $replyHtml,
    'clone'           => ['id' => $cloneId, 'code' => $clone['code'], 'name' => $clone['name']],
    'chatbox_id'      => $chatboxId,
    'regenerated'     => (bool)$regenerate,
    'currency'        => ['code' => $clone['currency'], 'earned' => $earned, 'delta' => $earned],
    'turn_ids'        => ['user' => $userTurnId, 'assistant' => $aiTurnId],
    'provider'        => $provider,
    'model'           => $modelUsed,
    'tokens'          => $tokens,
    'providers_tried' => $providersTried,
    'state_delta'     => $delta,
    'next_action'     => $nextAction,
    'paradoxes'       => $paradoxes,
    'state' => [
        'interaction_count'  => $state['interaction_count'],
        'recursion_depth'    => $state['recursion_depth'],
        'SAX_total'          => $state['token_economy_balance']['SAX_total'] ?? 0,
        'paradox_holds'      => count($state['paradox_holds']   ?? []),
        'future_memories'    => count($state['future_memories'] ?? []),
    ],
    'offline' => $usedOffline,
]);
