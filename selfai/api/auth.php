<?php
/** SelfAI — Authentication endpoint */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();

$action = $_GET['action'] ?? selfai_body()['action'] ?? 'me';
$body = selfai_body();

try {
    if ($action === 'signup') {
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $name = trim($body['display_name'] ?? '') ?: explode('@', $email)[0];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            selfai_json(['error' => 'email_or_password_invalid'], 400);
        }

        $db = selfai_system_db();
        $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) selfai_json(['error' => 'email_already_registered'], 409);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $db->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)");
        $ins->execute([$email, $name, $hash]);
        $uid = (int)$db->lastInsertId();

        // Create per-user db
        selfai_user_db($uid);

        $_SESSION['user_id'] = $uid;
        selfai_json(['ok' => true, 'user' => ['id' => $uid, 'email' => $email, 'display_name' => $name]]);
    }

    if ($action === 'login') {
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $db = selfai_system_db();
        $stmt = $db->prepare("SELECT id, email, display_name, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            selfai_json(['error' => 'invalid_credentials'], 401);
        }
        $_SESSION['user_id'] = (int)$u['id'];
        $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?")->execute([$u['id']]);
        selfai_json(['ok' => true, 'user' => ['id' => (int)$u['id'], 'email' => $u['email'], 'display_name' => $u['display_name']]]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (session_id()) session_destroy();
        selfai_json(['ok' => true]);
    }

    if ($action === 'me') {
        $u = selfai_current_user();
        if (!$u) selfai_json(['authenticated' => false]);
        selfai_json(['authenticated' => true, 'user' => $u]);
    }

    selfai_json(['error' => 'unknown_action'], 400);
} catch (Throwable $e) {
    selfai_json(['error' => 'server_error', 'detail' => $e->getMessage()], 500);
}
