<?php
/**
 * SelfAI — SQLite helper
 * Each user has their own database file: database/user_<id>.db
 * A shared system database holds users + sessions: database/system.db
 */

declare(strict_types=1);

define('SELFAI_ROOT', dirname(__DIR__));
define('SELFAI_DB_DIR', SELFAI_ROOT . '/database');

if (!is_dir(SELFAI_DB_DIR)) {
    mkdir(SELFAI_DB_DIR, 0775, true);
}

// ---- Security hardening: HttpOnly + SameSite session cookies ----
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
}

/** System DB (users + global config) */
function selfai_system_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . SELFAI_DB_DIR . '/system.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            display_name TEXT,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME
        )");
    }
    return $pdo;
}

/** Per-user DB */
function selfai_user_db(int $userId): PDO {
    static $cache = [];
    if (!isset($cache[$userId])) {
        $path = SELFAI_DB_DIR . "/user_{$userId}.db";
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Conversations
        $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clone_id TEXT NOT NULL,
            clone_name TEXT NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            feedback INTEGER DEFAULT 0,
            tokens_used INTEGER DEFAULT 0,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Chatboxes — one SelfAI clone can host many parallel chat sessions per user.
        // Each chatbox is aware of every other chatbox for the same clone (cross-chatbox memory).
        $pdo->exec("CREATE TABLE IF NOT EXISTS chatboxes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clone_id TEXT NOT NULL,
            title TEXT NOT NULL DEFAULT 'New chatbox',
            summary TEXT DEFAULT '',
            archived INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Add chatbox_id to conversations if upgrading an older DB.
        try {
            $cols = $pdo->query("PRAGMA table_info(conversations)")->fetchAll(PDO::FETCH_ASSOC);
            $haveChatbox = false;
            foreach ($cols as $c) if (($c['name'] ?? '') === 'chatbox_id') { $haveChatbox = true; break; }
            if (!$haveChatbox) {
                $pdo->exec("ALTER TABLE conversations ADD COLUMN chatbox_id INTEGER DEFAULT 0");
            }
        } catch (Throwable $e) { /* best-effort migration */ }

        // Resumes / uploaded text
        $pdo->exec("CREATE TABLE IF NOT EXISTS resumes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            file_content TEXT,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Long-term attributes for the user's SelfAI profile
        $pdo->exec("CREATE TABLE IF NOT EXISTS data_selfai (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attribute_key TEXT UNIQUE NOT NULL,
            attribute_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // API call logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS deepseek_apis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint TEXT NOT NULL,
            clone_id TEXT,
            request_payload TEXT,
            response TEXT,
            tokens_used INTEGER DEFAULT 0,
            provider TEXT DEFAULT 'deepseek',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2030B currency ledger (CTC, TIC, VTC, INC, SCC, WPC, WDC, JEC, FLC, GRC, SAX)
        $pdo->exec("CREATE TABLE IF NOT EXISTS currency_ledger (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            currency TEXT NOT NULL,
            amount INTEGER NOT NULL,
            source_clone TEXT,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $cache[$userId] = $pdo;
    }
    return $cache[$userId];
}

/** Helper to read config.json */
function selfai_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = json_decode(file_get_contents(SELFAI_ROOT . '/api/config.json'), true);
        if (!$cfg) {
            throw new RuntimeException('Missing or invalid api/config.json');
        }
    }
    return $cfg;
}

/** Find clone by id */
function selfai_clone(string $id): ?array {
    foreach (selfai_config()['clones'] as $c) {
        if ($c['id'] === $id) return $c;
    }
    return null;
}

/** Load .env file (very small parser) */
function selfai_env(string $key, string $default = ''): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = SELFAI_ROOT . '/api/.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
        // Allow process env to override
        foreach ($env as $k => $_) {
            if (getenv($k) !== false) $env[$k] = getenv($k);
        }
    }
    return $env[$key] ?? (getenv($key) !== false ? getenv($key) : $default);
}

/** Auth helpers */
function selfai_current_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('selfai_sid');
        session_start();
    }
    if (empty($_SESSION['user_id'])) return null;
    $stmt = selfai_system_db()->prepare("SELECT id, email, display_name, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function selfai_require_user(): array {
    $u = selfai_current_user();
    if (!$u) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }
    return $u;
}

/** JSON response helper */
/** UTF-8 safe substring with graceful fallback when mbstring isn't available. */
function selfai_clip(string $s, int $len): string {
    if ($len <= 0 || $s === '') return '';
    if (function_exists('mb_substr')) return mb_substr($s, 0, $len, 'UTF-8');
    // Fallback: walk bytes but respect multi-byte boundaries.
    if (strlen($s) <= $len) return $s;
    $out = substr($s, 0, $len);
    // Trim a possibly-broken trailing UTF-8 sequence
    while ($out !== '' && (ord(substr($out, -1)) & 0xC0) === 0x80) {
        $out = substr($out, 0, -1);
    }
    return $out;
}

function selfai_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read JSON body */
function selfai_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

/**
 * Long-lifetime memory — rolling per-clone summary stored in data_selfai.
 * Key pattern:  memory.summary.<clone_id>
 * Key pattern:  memory.facts.<clone_id>   (compact JSON of stable facts)
 */
function selfai_memory_key(string $cloneId, string $kind = 'summary'): string {
    return "memory.{$kind}." . $cloneId;
}

function selfai_get_memory(PDO $udb, string $cloneId, string $kind = 'summary'): string {
    $stmt = $udb->prepare("SELECT attribute_value FROM data_selfai WHERE attribute_key = ?");
    $stmt->execute([selfai_memory_key($cloneId, $kind)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['attribute_value'] : '';
}

function selfai_set_memory(PDO $udb, string $cloneId, string $value, string $kind = 'summary'): void {
    $key = selfai_memory_key($cloneId, $kind);
    $udb->prepare("INSERT INTO data_selfai (attribute_key, attribute_value, updated_at)
                   VALUES (?, ?, CURRENT_TIMESTAMP)
                   ON CONFLICT(attribute_key) DO UPDATE SET
                     attribute_value = excluded.attribute_value,
                     updated_at = CURRENT_TIMESTAMP")
        ->execute([$key, $value]);
}

/** Count conversation turns for a clone */
function selfai_turn_count(PDO $udb, string $cloneId): int {
    $s = $udb->prepare("SELECT COUNT(*) FROM conversations WHERE clone_id = ?");
    $s->execute([$cloneId]);
    return (int)$s->fetchColumn();
}

/** Get all attribute facts as compact array, for hydrating system prompts */
function selfai_user_attributes(PDO $udb, int $limit = 40): array {
    $stmt = $udb->query("SELECT attribute_key, attribute_value FROM data_selfai
                         WHERE attribute_key NOT LIKE 'memory.%'
                         ORDER BY updated_at DESC LIMIT " . (int)$limit);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['attribute_key']] = $r['attribute_value'];
    }
    return $out;
}

/* =========================================================
 * Internationalization (i18n)
 * Stored in selfai/api/i18n.json; locale resolved from:
 *   1. ?lang=xx URL override (sets a cookie)
 *   2. selfai_locale cookie
 *   3. Accept-Language header
 *   4. fallback to 'en'
 * ========================================================= */

function selfai_i18n_load(): array {
    static $i18n = null;
    if ($i18n !== null) return $i18n;
    $path = SELFAI_ROOT . '/api/i18n.json';
    if (!is_readable($path)) {
        $i18n = ['_meta' => ['default_locale' => 'en', 'locales' => ['en']], 'locales_info' => [], 'strings' => []];
        return $i18n;
    }
    $raw = file_get_contents($path);
    $j = json_decode($raw, true);
    $i18n = is_array($j) ? $j : ['_meta' => ['default_locale' => 'en', 'locales' => ['en']], 'locales_info' => [], 'strings' => []];
    return $i18n;
}

function selfai_locales(): array {
    $i = selfai_i18n_load();
    return $i['_meta']['locales'] ?? ['en'];
}

function selfai_default_locale(): string {
    $i = selfai_i18n_load();
    return $i['_meta']['default_locale'] ?? 'en';
}

/** Resolve effective locale for this request and persist it in a cookie. */
function selfai_resolve_locale(): string {
    static $resolved = null;
    if ($resolved !== null) return $resolved;
    $available = selfai_locales();
    $default = selfai_default_locale();
    $pick = $default;

    // 1) URL override
    if (!empty($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
        $pick = $_GET['lang'];
        setcookie('selfai_locale', $pick, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => false,
        ]);
        $_COOKIE['selfai_locale'] = $pick;
    }
    // 2) Cookie
    elseif (!empty($_COOKIE['selfai_locale']) && in_array($_COOKIE['selfai_locale'], $available, true)) {
        $pick = $_COOKIE['selfai_locale'];
    }
    // 3) Accept-Language header
    elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $hdr = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach (explode(',', $hdr) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            $base = substr($tag, 0, 2);
            if (in_array($base, $available, true)) { $pick = $base; break; }
        }
    }
    $resolved = $pick;
    return $resolved;
}

/** Get info object {code,name,native,dir,flag} for a locale. */
function selfai_locale_info(?string $locale = null): array {
    $i = selfai_i18n_load();
    $locale = $locale ?: selfai_resolve_locale();
    return $i['locales_info'][$locale] ?? ['code' => $locale, 'name' => $locale, 'native' => $locale, 'dir' => 'ltr', 'flag' => ''];
}

/** Get a flat dictionary of translations for the active locale,
 *  prefixed like "common.send", "chat.history". */
function selfai_translations(?string $locale = null): array {
    $i = selfai_i18n_load();
    $locale = $locale ?: selfai_resolve_locale();
    $fallback = selfai_default_locale();
    $out = [];
    foreach (($i['strings'] ?? []) as $section => $keys) {
        if (!is_array($keys)) continue;
        foreach ($keys as $k => $entry) {
            if (!is_array($entry)) continue;
            $val = $entry[$locale] ?? $entry[$fallback] ?? '';
            $out["{$section}.{$k}"] = $val;
        }
    }
    return $out;
}

/** Translate a dotted key, with optional sprintf-style args. */
function selfai_t(string $key, array $args = [], ?string $locale = null): string {
    $tr = selfai_translations($locale);
    $val = $tr[$key] ?? $key;
    if ($args) {
        $val = strtr($val, $args);
    }
    return $val;
}

/* =========================================================
 * Markdown → safe HTML renderer for AI replies
 * Supports a SMALL whitelist: headings, bold, italic, inline code, code blocks,
 * unordered/ordered lists, links (relative or https), paragraphs, line breaks,
 * blockquotes. Everything else is escaped.
 * Output: <h1>..<h3>, <strong>, <em>, <code>, <pre><code>, <ul>/<ol>/<li>,
 * <a href target rel>, <p>, <br>, <blockquote>.
 * ========================================================= */
function selfai_md_to_html(string $md): string {
    // Normalize line endings
    $md = str_replace(["\r\n", "\r"], "\n", $md);

    // Step 1: pull out fenced code blocks so we don't process markdown inside them.
    $codes = [];
    $md = preg_replace_callback('/```([a-zA-Z0-9_+-]*)\n(.*?)```/s', function ($m) use (&$codes) {
        $lang = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        $body = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
        $key  = "\x01CODE" . count($codes) . "\x01";
        $codes[$key] = '<pre class="md-pre"><code class="md-code" data-lang="' . $lang . '">' . $body . '</code></pre>';
        return $key;
    }, $md);

    // Step 2: escape everything else for safety, then walk-back the inline tokens.
    $md = htmlspecialchars($md, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Inline code  `code`
    $md = preg_replace_callback('/`([^`\n]+)`/', function ($m) {
        return '<code class="md-inline">' . $m[1] . '</code>';
    }, $md);

    // Bold  **text**  __text__
    $md = preg_replace('/\*\*([^*\n]+?)\*\*/', '<strong>$1</strong>', $md);
    $md = preg_replace('/__([^_\n]+?)__/',     '<strong>$1</strong>', $md);

    // Italic  *text*  _text_  (avoid eating bold; bold already replaced)
    $md = preg_replace('/(?<![\*\w])\*([^\*\n]+?)\*(?!\*)/', '<em>$1</em>', $md);
    $md = preg_replace('/(?<![_\w])_([^_\n]+?)_(?!_)/',     '<em>$1</em>', $md);

    // Links  [text](https://… or relative)
    $md = preg_replace_callback('/\[([^\]\n]+)\]\(([^()\s]+)\)/', function ($m) {
        $text = $m[1];
        $url  = $m[2];
        // allow http(s), mailto, and relative paths starting with / or #
        if (!preg_match('#^(https?:|mailto:|/|\#)#i', $url)) {
            $url = '#';
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<a class="md-link" href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
    }, $md);

    // Block-level pass: split on blank lines, decide each block.
    $blocks = preg_split('/\n{2,}/', trim($md));
    $html = [];
    foreach ($blocks as $block) {
        $block = trim($block, "\n");
        if ($block === '') continue;
        if (str_starts_with($block, "\x01CODE")) {            // restored code block placeholder
            $html[] = $block;
            continue;
        }
        // Headings
        if (preg_match('/^(#{1,3})\s+(.+)$/m', $block) && substr_count($block, "\n") === 0) {
            preg_match('/^(#{1,3})\s+(.+)$/', $block, $m);
            $lvl = strlen($m[1]);
            $html[] = "<h{$lvl} class=\"md-h md-h{$lvl}\">{$m[2]}</h{$lvl}>";
            continue;
        }
        // Blockquote
        if (str_starts_with($block, '&gt; ') || str_starts_with($block, '> ')) {
            $lines = preg_split('/\n/', $block);
            $cleaned = array_map(fn($l) => preg_replace('/^(&gt;|>)\s?/', '', $l), $lines);
            $html[] = '<blockquote class="md-quote">' . implode('<br>', $cleaned) . '</blockquote>';
            continue;
        }
        // Unordered list
        if (preg_match('/^\s*[-*]\s+/', $block)) {
            $items = preg_split('/\n(?=\s*[-*]\s+)/', $block);
            $items = array_map(fn($i) => preg_replace('/^\s*[-*]\s+/', '', $i), $items);
            $html[] = '<ul class="md-list">' . implode('', array_map(fn($i) => '<li>' . nl2br(trim($i)) . '</li>', $items)) . '</ul>';
            continue;
        }
        // Ordered list
        if (preg_match('/^\s*\d+\.\s+/', $block)) {
            $items = preg_split('/\n(?=\s*\d+\.\s+)/', $block);
            $items = array_map(fn($i) => preg_replace('/^\s*\d+\.\s+/', '', $i), $items);
            $html[] = '<ol class="md-list md-list-ol">' . implode('', array_map(fn($i) => '<li>' . nl2br(trim($i)) . '</li>', $items)) . '</ol>';
            continue;
        }
        // Default → paragraph with <br> for soft newlines
        $html[] = '<p class="md-p">' . nl2br($block) . '</p>';
    }

    $out = implode("\n", $html);
    // Restore fenced code blocks
    foreach ($codes as $k => $v) $out = str_replace($k, $v, $out);
    return $out;
}

/**
 * Strip the trailing "<CURRENCY> earned this turn: N" line from an AI reply
 * so it can be displayed in the bubble footer instead of inside the body.
 * Returns [cleanText, earnedInt(1..5)].
 */
function selfai_strip_currency_tail(string $aiText, string $currency): array {
    $earned = 1;
    $pattern = '/\R?\s*' . preg_quote($currency, '/') . '[^0-9\n\r]{0,30}(\d+)[^\n\r]*$/u';
    if (preg_match($pattern, $aiText, $m)) {
        $earned = max(1, min(5, (int)$m[1]));
        $aiText = preg_replace($pattern, '', $aiText);
    } else if (preg_match('/' . preg_quote($currency, '/') . '[^0-9]{0,30}(\d+)/u', $aiText, $m2)) {
        // Fallback: any inline mention; keep text intact but capture.
        $earned = max(1, min(5, (int)$m2[1]));
    }
    return [rtrim($aiText), $earned];
}

/* =========================================================================
 *  Chatbox helpers (per-clone, per-user parallel chat sessions)
 * ========================================================================= */

/** List chatboxes for a clone (newest first). */
function selfai_chatboxes_list(PDO $udb, string $cloneId, bool $includeArchived = false): array {
    $sql = "SELECT id, clone_id, title, summary, archived, created_at, updated_at
            FROM chatboxes WHERE clone_id = ?";
    if (!$includeArchived) $sql .= " AND archived = 0";
    $sql .= " ORDER BY updated_at DESC, id DESC";
    $stmt = $udb->prepare($sql);
    $stmt->execute([$cloneId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Ensure at least one chatbox exists for this clone; return its id. */
function selfai_chatbox_default(PDO $udb, string $cloneId): int {
    $stmt = $udb->prepare("SELECT id FROM chatboxes WHERE clone_id = ? AND archived = 0
                           ORDER BY updated_at DESC, id DESC LIMIT 1");
    $stmt->execute([$cloneId]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) return $id;
    return selfai_chatbox_create($udb, $cloneId, 'Main chatbox');
}

function selfai_chatbox_create(PDO $udb, string $cloneId, string $title = 'New chatbox'): int {
    $title = trim($title) !== '' ? trim($title) : 'New chatbox';
    $udb->prepare("INSERT INTO chatboxes (clone_id, title) VALUES (?, ?)")
        ->execute([$cloneId, $title]);
    return (int)$udb->lastInsertId();
}

function selfai_chatbox_touch(PDO $udb, int $chatboxId): void {
    $udb->prepare("UPDATE chatboxes SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$chatboxId]);
}

function selfai_chatbox_rename(PDO $udb, int $chatboxId, string $title): bool {
    $title = trim($title);
    if ($title === '') return false;
    $udb->prepare("UPDATE chatboxes SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$title, $chatboxId]);
    return true;
}

function selfai_chatbox_archive(PDO $udb, int $chatboxId): bool {
    $udb->prepare("UPDATE chatboxes SET archived = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$chatboxId]);
    return true;
}

/** Set a stored short summary for a chatbox (used in cross-chatbox awareness). */
function selfai_chatbox_set_summary(PDO $udb, int $chatboxId, string $summary): void {
    $udb->prepare("UPDATE chatboxes SET summary = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([selfai_clip($summary, 800), $chatboxId]);
}

/**
 * Build a brief "cross-chatbox awareness" block: tells the active chatbox
 * about *other* chatboxes (same clone), so it knows the user has parallel threads.
 */
function selfai_chatbox_awareness_block(PDO $udb, string $cloneId, int $activeChatboxId): string {
    $stmt = $udb->prepare("SELECT id, title, summary, updated_at FROM chatboxes
                           WHERE clone_id = ? AND archived = 0 AND id <> ?
                           ORDER BY updated_at DESC LIMIT 6");
    $stmt->execute([$cloneId, $activeChatboxId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) return '';
    $lines = ["", "### Sibling chatboxes (same SelfAI clone, parallel threads)"];
    foreach ($rows as $r) {
        $sum = trim((string)($r['summary'] ?? ''));
        if ($sum === '') {
            // Derive from the last user message in that chatbox
            $q = $udb->prepare("SELECT content FROM conversations
                                WHERE clone_id = ? AND chatbox_id = ? AND role = 'user'
                                ORDER BY id DESC LIMIT 1");
            $q->execute([$cloneId, (int)$r['id']]);
            $last = (string)($q->fetchColumn() ?: '');
            $sum  = $last !== '' ? selfai_clip($last, 160) : '(no messages yet)';
        }
        $lines[] = "- #{$r['id']} \"" . trim((string)$r['title']) . "\" — " . $sum;
    }
    $lines[] = "You may reference these parallel sessions if the user asks about other ongoing conversations, but stay focused on the current chatbox.";
    return implode("\n", $lines);
}
