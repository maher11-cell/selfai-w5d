<?php
/**
 * SelfAI — Chat endpoint
 * Provider order (all OpenAI-compatible):
 *   1. DeepSeek V4 (deepseek-chat)                 — primary if key + balance OK
 *   2. OpenRouter (multi-model retry over free tier) — needs HTTP-Referer + X-Title
 *   3. Groq (llama-3.3-70b-versatile)
 *   4. Google Gemini (gemini-2.0-flash-exp)
 *   5. Hugging Face Inference Router
 *   6. Offline preview (always succeeds)
 *
 * 100% free-API friendly: OpenRouter free models rate-limit individually,
 * so we try a small ordered list and accept the first 2xx reply.
 */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();
$user = selfai_require_user();
$body = selfai_body();

$cloneId   = (string)($body['clone_id'] ?? '');
$message   = trim((string)($body['message'] ?? ''));
$chatboxId = (int)($body['chatbox_id'] ?? 0);
$regenerate = !empty($body['regenerate']);
if ($cloneId === '' || $message === '') selfai_json(['error' => 'clone_id_and_message_required'], 400);

// SelfAI-X runs the eternal loop in a dedicated endpoint.
if ($cloneId === 'selfai_x_master') {
    require __DIR__ . '/selfai_x.php';
    exit;
}

$clone = selfai_clone($cloneId);
if (!$clone) selfai_json(['error' => 'unknown_clone'], 404);

$cfg = selfai_config();
$udb = selfai_user_db((int)$user['id']);

// Resolve/auto-create the active chatbox for this clone
if ($chatboxId <= 0) {
    $chatboxId = selfai_chatbox_default($udb, $cloneId);
} else {
    // Validate ownership: the chatbox must belong to this user's DB + same clone
    $own = $udb->prepare("SELECT id FROM chatboxes WHERE id = ? AND clone_id = ? AND archived = 0");
    $own->execute([$chatboxId, $cloneId]);
    if (!$own->fetchColumn()) {
        $chatboxId = selfai_chatbox_default($udb, $cloneId);
    }
}

$maxCtx       = (int)($cfg['max_context_messages'] ?? 12);
$summaryAfter = (int)($cfg['summary_after_turns']  ?? 16);
$summaryKeep  = (int)($cfg['summary_keep_recent']  ?? 8);

// Pull last N turns for context (scoped to this chatbox)
$ctxStmt = $udb->prepare("SELECT role, content FROM conversations
                          WHERE clone_id = ? AND chatbox_id = ?
                          ORDER BY id DESC LIMIT ?");
$ctxStmt->bindValue(1, $cloneId, PDO::PARAM_STR);
$ctxStmt->bindValue(2, $chatboxId, PDO::PARAM_INT);
$ctxStmt->bindValue(3, $maxCtx, PDO::PARAM_INT);
$ctxStmt->execute();
$context = array_reverse($ctxStmt->fetchAll(PDO::FETCH_ASSOC));

// ---- Long-lifetime memory: rolling summary + stable user attributes ----
$memorySummary = selfai_get_memory($udb, $cloneId, 'summary');
$userAttrs     = selfai_user_attributes($udb, 40);

$systemPrompt = $clone['system_prompt'];
$memoryBlock  = '';
if ($memorySummary !== '') {
    $memoryBlock .= "\n\n### Long-Term Memory (rolling summary of past sessions with this user)\n" . $memorySummary;
}
if (!empty($userAttrs)) {
    $facts = [];
    foreach ($userAttrs as $k => $v) {
        if ((string)$v !== '') $facts[] = "- {$k}: " . (string)$v;
    }
    if ($facts) {
        $memoryBlock .= "\n\n### User Profile Facts (data_selfai)\n" . implode("\n", array_slice($facts, 0, 25));
    }
}
// Active UI locale — instruct the AI to answer in that language while keeping the
// Qur'anic ayah verbatim in Arabic and the currency line in Latin letters (regex-parseable).
$locale  = selfai_resolve_locale();
$locInfo = selfai_locale_info($locale);
$langName = $locInfo['name'] ?? 'English';
$langDir  = $locInfo['dir']  ?? 'ltr';

$memoryBlock .= "\n\n### Operating Rules\n" .
                "- Respond primarily in {$langName} (locale code: {$locale}, text direction: {$langDir}).\n" .
                "- Keep the Qur'anic ayah verbatim in Arabic: " . ($clone['ayah'] ?? '') . " (you may briefly translate it once if useful).\n" .
                "- Always end your reply with exactly this Latin-letter line:  " . $clone['currency'] . " earned this turn: N  (where N is 1–5).\n" .
                "- Be specific, concise, and honor the verse above.\n" .
                "- You may use light Markdown (headings, **bold**, *italic*, lists, > quotes, `code`, ```code blocks```, [links](https://...)). It will be rendered to HTML.";

// Cross-chatbox awareness — this chatbox knows about sibling chatboxes for the same clone.
$siblingsBlock = selfai_chatbox_awareness_block($udb, $cloneId, $chatboxId);
if ($siblingsBlock !== '') $memoryBlock .= "\n" . $siblingsBlock;

$systemPrompt .= $memoryBlock;

// Build message stack
$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($context as $row) $messages[] = ['role' => $row['role'], 'content' => $row['content']];
$messages[] = ['role' => 'user', 'content' => $message];

/**
 * Call an OpenAI-compatible chat endpoint.
 * @param array $extraHeaders Optional extra headers (e.g. OpenRouter requires HTTP-Referer / X-Title).
 */
function selfai_call_provider(
    string $endpoint,
    string $apiKey,
    string $model,
    array $messages,
    float $temp,
    array $extraHeaders = []
): array {
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temp,
        'max_tokens' => 1024,
    ], JSON_UNESCAPED_UNICODE);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];
    foreach ($extraHeaders as $h) $headers[] = $h;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        return ['ok' => false, 'status' => $code, 'error' => "HTTP $code $err", 'raw' => $resp];
    }
    $j = json_decode($resp, true);
    $text = $j['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        return ['ok' => false, 'status' => $code, 'error' => 'empty_completion', 'raw' => $resp];
    }
    $tokens = $j['usage']['total_tokens'] ?? 0;
    return ['ok' => true, 'text' => $text, 'tokens' => (int)$tokens, 'raw' => $resp];
}

$temp = (float)($cfg['temperature'] ?? 0.7);
$providersTried = [];
$result = null;

// ---------- 1) DeepSeek V4 (primary) ----------
$deepKey = selfai_env('DEEPSEEK_API_KEY');
if ($deepKey !== '') {
    $providersTried[] = 'deepseek';
    $try = selfai_call_provider(
        $cfg['deepseek_endpoint'] ?? 'https://api.deepseek.com/v1/chat/completions',
        $deepKey,
        $cfg['deepseek_v4_model'] ?? 'deepseek-chat',
        $messages,
        $temp
    );
    if ($try['ok']) { $try['provider'] = 'deepseek'; $result = $try; }
}

// ---------- 2) OpenRouter (multi-model retry over free tier) ----------
if (!$result) {
    $orKey = selfai_env('OPENROUTER_API_KEY');
    if ($orKey !== '') {
        // OpenRouter REQUIRES these headers for the free tier, otherwise it 401/403/blocks.
        $appUrl   = selfai_env('APP_URL', 'https://2030b.com/selfai');
        $appTitle = selfai_env('APP_TITLE', 'SelfAI · 2030B Ecosystem');
        $orHeaders = [
            'HTTP-Referer: ' . $appUrl,
            'X-Title: ' . $appTitle,
        ];
        // Try a small ordered list — first 200 wins, 429s/5xx fall through.
        $orModels = $cfg['openrouter_models'] ?? [
            'deepseek/deepseek-v4-flash:free',
            'openai/gpt-oss-20b:free',
            'meta-llama/llama-3.3-70b-instruct:free',
            'google/gemma-4-26b-a4b-it:free',
            'qwen/qwen3-next-80b-a3b-instruct:free',
        ];
        foreach ($orModels as $model) {
            $providersTried[] = 'openrouter:' . $model;
            $try = selfai_call_provider(
                'https://openrouter.ai/api/v1/chat/completions',
                $orKey, $model, $messages, $temp, $orHeaders
            );
            if ($try['ok']) {
                $try['provider'] = 'openrouter';
                $try['model']    = $model;
                $result = $try;
                break;
            }
            // 401/403 => key issue, stop retrying OpenRouter
            if (in_array(($try['status'] ?? 0), [401, 403], true)) break;
        }
    }
}

// ---------- 3) Groq ----------
if (!$result) {
    $k = selfai_env('GROQ_API_KEY');
    if ($k !== '') {
        $providersTried[] = 'groq';
        $try = selfai_call_provider(
            'https://api.groq.com/openai/v1/chat/completions',
            $k, 'llama-3.3-70b-versatile', $messages, $temp
        );
        if ($try['ok']) { $try['provider'] = 'groq'; $result = $try; }
    }
}

// ---------- 4) Gemini ----------
if (!$result) {
    $k = selfai_env('GEMINI_API_KEY');
    if ($k !== '') {
        $providersTried[] = 'gemini';
        $try = selfai_call_provider(
            'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            $k, 'gemini-2.0-flash-exp', $messages, $temp
        );
        if ($try['ok']) { $try['provider'] = 'gemini'; $result = $try; }
    }
}

// ---------- 5) Hugging Face ----------
if (!$result) {
    $k = selfai_env('HF_TOKEN');
    if ($k !== '') {
        $providersTried[] = 'huggingface';
        $try = selfai_call_provider(
            'https://router.huggingface.co/v1/chat/completions',
            $k, 'meta-llama/Llama-3.2-3B-Instruct', $messages, $temp
        );
        if ($try['ok']) { $try['provider'] = 'huggingface'; $result = $try; }
    }
}

// ---------- 6) Offline pedagogical fallback ----------
$usedOffline = false;
if (!$result) {
    $usedOffline = true;
    $providersTried[] = 'offline_fallback';
    $offline = "[" . $clone['code'] . " · offline preview mode]\n\n" .
        "You said: " . $message . "\n\n" .
        "No live AI provider answered right now (all configured keys returned errors or none are set). " .
        "Configure or top up DEEPSEEK_API_KEY, or rely on the free OpenRouter / Groq / Gemini / Hugging Face tiers in selfai/api/.env.\n\n" .
        "Meanwhile, here is what " . $clone['code'] . " would normally do for this turn:\n" .
        "- Honor the verse " . $clone['ayah'] . "\n" .
        "- Run its 3-step audit (see /selfai docs)\n" .
        "- End with: '" . $clone['currency'] . " earned this turn: 1'";
    $result = ['ok' => true, 'text' => $offline, 'tokens' => 0, 'provider' => 'offline_fallback', 'raw' => ''];
}

$aiText   = $result['text'];
$tokens   = (int)($result['tokens'] ?? 0);
$provider = $result['provider'];
$modelUsed = $result['model'] ?? ($cfg['deepseek_v4_model'] ?? 'deepseek-chat');

// Strip the trailing "<CURRENCY> earned this turn: N" line so it lives in the bubble footer,
// not in the body of the message. Returns [cleanedText, earnedAmount].
[$aiTextClean, $earnedFromBody] = selfai_strip_currency_tail($aiText, $clone['currency']);

// Render Markdown → safe HTML for the UI (the raw clean text is also returned for copy/regen).
$replyHtml = selfai_md_to_html($aiTextClean);

// Store user + assistant turns (scoped to chatbox)
$ins = $udb->prepare("INSERT INTO conversations (clone_id, clone_name, chatbox_id, role, content, tokens_used)
                      VALUES (?, ?, ?, ?, ?, ?)");
$ins->execute([$cloneId, $clone['name'], $chatboxId, 'user', $message, 0]);
$userTurnId = (int)$udb->lastInsertId();
$ins->execute([$cloneId, $clone['name'], $chatboxId, 'assistant', $aiTextClean, $tokens]);
$aiTurnId = (int)$udb->lastInsertId();

// Bump chatbox updated_at + auto-title on first user message
selfai_chatbox_touch($udb, $chatboxId);
$q = $udb->prepare("SELECT title FROM chatboxes WHERE id = ?");
$q->execute([$chatboxId]);
$boxTitle = (string)($q->fetchColumn() ?: '');
if ($boxTitle === 'New chatbox' || $boxTitle === 'Main chatbox' || $boxTitle === '') {
    $autoTitle = selfai_clip(trim(preg_replace('/\s+/', ' ', $message)), 60);
    if ($autoTitle !== '') selfai_chatbox_rename($udb, $chatboxId, $autoTitle);
}

// Log API call (no raw key leakage)
$udb->prepare("INSERT INTO deepseek_apis (endpoint, clone_id, request_payload, response, tokens_used, provider) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([
        $provider . ':' . $modelUsed,
        $cloneId,
        substr(json_encode(['messages' => $messages]), 0, 8000),
        substr($result['raw'] ?? '', 0, 8000),
        $tokens,
        $provider,
    ]);

// Award 2030B currency: value already parsed from the stripped tail line.
$cur    = $clone['currency'];
$earned = $earnedFromBody;
$udb->prepare("INSERT INTO currency_ledger (currency, amount, source_clone, note) VALUES (?, ?, ?, ?)")
    ->execute([$cur, $earned, $cloneId, 'chat_turn:' . $aiTurnId]);

// ---------- Long-memory consolidation ----------
// Every $summaryKeep turns past the threshold, compress older turns into a rolling summary.
$summaryStatus = 'skipped';
if (!$usedOffline) {
    $total = selfai_turn_count($udb, $cloneId);
    if ($total >= $summaryAfter && ($total % $summaryKeep) === 0) {
        $excess = $total - $summaryKeep;
        if ($excess >= 6) {
            $older = $udb->prepare("SELECT role, content FROM conversations
                                    WHERE clone_id = ? ORDER BY id ASC LIMIT ?");
            $older->bindValue(1, $cloneId, PDO::PARAM_STR);
            $older->bindValue(2, $excess,  PDO::PARAM_INT);
            $older->execute();
            $olderRows = $older->fetchAll(PDO::FETCH_ASSOC);

            $prior = selfai_get_memory($udb, $cloneId, 'summary');
            $sumMessages = [
                ['role' => 'system', 'content' =>
                    "You are a memory compressor for SelfAI " . $clone['code'] . ".\n" .
                    "Produce a tight, factual third-person summary (≤250 words) of the conversation below.\n" .
                    "Preserve: user's goals, stable preferences, decisions, names, projects, dates, commitments.\n" .
                    "Drop: pleasantries, currency tallies, repeated wording.\n" .
                    "If a prior summary exists, merge it with the new turns (do NOT lose prior facts)."],
                ['role' => 'user', 'content' =>
                    ($prior !== '' ? "Prior summary:\n{$prior}\n\n" : '') .
                    "New turns to compress:\n" .
                    implode("\n", array_map(fn($r) => strtoupper($r['role']) . ': ' . $r['content'], $olderRows))],
            ];

            // Reuse provider chain — single shot via OpenRouter first (cheapest free path).
            $sumRes = null;
            $orKey = selfai_env('OPENROUTER_API_KEY');
            if ($orKey !== '') {
                $orHeaders = [
                    'HTTP-Referer: ' . selfai_env('APP_URL', 'https://2030b.com/selfai'),
                    'X-Title: '      . selfai_env('APP_TITLE', 'SelfAI · 2030B Ecosystem'),
                ];
                foreach (['openai/gpt-oss-20b:free', 'z-ai/glm-4.5-air:free', 'deepseek/deepseek-v4-flash:free'] as $sm) {
                    $st = selfai_call_provider('https://openrouter.ai/api/v1/chat/completions',
                                               $orKey, $sm, $sumMessages, 0.2, $orHeaders);
                    if ($st['ok']) { $sumRes = $st; break; }
                }
            }
            if ($sumRes && $sumRes['ok'] && ($sumRes['text'] ?? '') !== '') {
                selfai_set_memory($udb, $cloneId, $sumRes['text'], 'summary');
                $summaryStatus = 'updated';
            } else {
                $summaryStatus = 'failed';
            }
        }
    }
}

selfai_json([
    'ok'              => true,
    'reply'           => $aiTextClean,       // clean text (currency tail removed)
    'reply_html'      => $replyHtml,         // safe HTML for animated rendering
    'clone'           => ['id' => $cloneId, 'code' => $clone['code'], 'name' => $clone['name']],
    'chatbox_id'      => $chatboxId,
    'regenerated'     => (bool)$regenerate,
    'currency'        => ['code' => $cur, 'earned' => $earned, 'delta' => $earned], // negative = lose (reserved)
    'turn_ids'        => ['user' => $userTurnId, 'assistant' => $aiTurnId],
    'provider'        => $provider,
    'model'           => $modelUsed,
    'tokens'          => $tokens,
    'providers_tried' => $providersTried,
    'memory'          => [
        'summary_present' => $memorySummary !== '',
        'summary_status'  => $summaryStatus,
        'context_turns'   => count($context),
        'total_turns'     => selfai_turn_count($udb, $cloneId),
    ],
    'offline'         => $usedOffline,
]);
