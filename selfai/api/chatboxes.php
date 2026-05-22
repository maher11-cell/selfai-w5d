<?php
/**
 * SelfAI — Chatboxes endpoint
 * Per-clone, per-user parallel chat sessions. Each chatbox is aware of every
 * other chatbox for the same clone (cross-chatbox memory in system prompt).
 *
 *   GET  ?action=list&clone_id=...               → list all chatboxes for that clone
 *   POST action=create  { clone_id, title? }     → create new chatbox; returns id
 *   POST action=rename  { id, title }            → rename
 *   POST action=archive { id }                   → soft-delete (sets archived=1)
 *   POST action=set_summary { id, summary }      → store cross-thread summary
 */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();
$user = selfai_require_user();
$udb  = selfai_user_db((int)$user['id']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = ($method === 'POST') ? selfai_body() : [];
$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    if ($action === 'list') {
        $cloneId = (string)($_GET['clone_id'] ?? $body['clone_id'] ?? '');
        if ($cloneId === '') selfai_json(['error' => 'clone_id_required'], 400);
        $clone = selfai_clone($cloneId);
        if (!$clone) selfai_json(['error' => 'unknown_clone'], 404);

        // Ensure at least one chatbox exists
        selfai_chatbox_default($udb, $cloneId);
        $rows = selfai_chatboxes_list($udb, $cloneId, false);

        // Attach last user message preview + count for each chatbox
        $countStmt = $udb->prepare("SELECT COUNT(*) FROM conversations WHERE clone_id = ? AND chatbox_id = ?");
        $lastStmt  = $udb->prepare("SELECT content, timestamp FROM conversations
                                    WHERE clone_id = ? AND chatbox_id = ? AND role = 'user'
                                    ORDER BY id DESC LIMIT 1");
        foreach ($rows as &$r) {
            $countStmt->execute([$cloneId, (int)$r['id']]);
            $r['msg_count'] = (int)$countStmt->fetchColumn();
            $lastStmt->execute([$cloneId, (int)$r['id']]);
            $last = $lastStmt->fetch(PDO::FETCH_ASSOC);
            $r['last_user_message'] = $last ? selfai_clip((string)$last['content'], 140) : '';
            $r['last_timestamp']    = $last ? $last['timestamp'] : $r['updated_at'];
        }
        unset($r);

        selfai_json([
            'ok'        => true,
            'clone'     => ['id' => $cloneId, 'code' => $clone['code'], 'name' => $clone['name']],
            'chatboxes' => $rows,
        ]);
    }

    if ($action === 'create') {
        if ($method !== 'POST') selfai_json(['error' => 'method_not_allowed'], 405);
        $cloneId = (string)($body['clone_id'] ?? '');
        $title   = trim((string)($body['title'] ?? ''));
        if ($cloneId === '') selfai_json(['error' => 'clone_id_required'], 400);
        if (!selfai_clone($cloneId)) selfai_json(['error' => 'unknown_clone'], 404);
        if ($title === '') $title = 'New chatbox';
        $id = selfai_chatbox_create($udb, $cloneId, $title);
        selfai_json(['ok' => true, 'id' => $id, 'title' => $title, 'clone_id' => $cloneId]);
    }

    if ($action === 'rename') {
        if ($method !== 'POST') selfai_json(['error' => 'method_not_allowed'], 405);
        $id    = (int)($body['id'] ?? 0);
        $title = trim((string)($body['title'] ?? ''));
        if ($id <= 0 || $title === '') selfai_json(['error' => 'id_and_title_required'], 400);
        $ok = selfai_chatbox_rename($udb, $id, $title);
        selfai_json(['ok' => $ok, 'id' => $id, 'title' => $title]);
    }

    if ($action === 'archive') {
        if ($method !== 'POST') selfai_json(['error' => 'method_not_allowed'], 405);
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) selfai_json(['error' => 'id_required'], 400);
        selfai_chatbox_archive($udb, $id);
        selfai_json(['ok' => true, 'id' => $id]);
    }

    if ($action === 'set_summary') {
        if ($method !== 'POST') selfai_json(['error' => 'method_not_allowed'], 405);
        $id      = (int)($body['id'] ?? 0);
        $summary = (string)($body['summary'] ?? '');
        if ($id <= 0) selfai_json(['error' => 'id_required'], 400);
        selfai_chatbox_set_summary($udb, $id, $summary);
        selfai_json(['ok' => true, 'id' => $id]);
    }

    selfai_json(['error' => 'unknown_action'], 400);
} catch (Throwable $e) {
    selfai_json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
}
