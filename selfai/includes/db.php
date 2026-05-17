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
