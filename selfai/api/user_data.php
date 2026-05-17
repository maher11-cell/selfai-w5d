<?php
/**
 * SelfAI — User data endpoint
 *   - GET  ?action=dashboard         → dashboard summary (score, ledger, recent)
 *   - GET  ?action=history&clone_id  → conversation history for one clone
 *   - POST action=attribute          → save a long-term attribute (e.g. learning_style)
 *   - POST action=resume             → save resume text
 *   - POST action=feedback           → store thumbs up/down (-1 / +1) for a turn
 */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();
$user = selfai_require_user();
$udb = selfai_user_db((int)$user['id']);

$action = $_GET['action'] ?? selfai_body()['action'] ?? 'dashboard';

try {
    if ($action === 'dashboard') {
        $cfg = selfai_config();
        $clones = $cfg['clones'];

        // Aggregates
        $totalConv = (int)$udb->query("SELECT COUNT(*) FROM conversations WHERE role='user'")->fetchColumn();
        $topRow = $udb->query("SELECT clone_id, clone_name, COUNT(*) c FROM conversations WHERE role='user' GROUP BY clone_id ORDER BY c DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $distinctClones = (int)$udb->query("SELECT COUNT(DISTINCT clone_id) FROM conversations")->fetchColumn();
        $attrCount = (int)$udb->query("SELECT COUNT(*) FROM data_selfai")->fetchColumn();
        $resumeCount = (int)$udb->query("SELECT COUNT(*) FROM resumes")->fetchColumn();

        // Feedback signal (-1 / 0 / +1 per ai turn)
        $fbAvg = $udb->query("SELECT AVG(feedback) FROM conversations WHERE role='assistant' AND feedback != 0")->fetchColumn();
        $fbAvg = $fbAvg === null ? 0 : (float)$fbAvg;

        // Currency totals
        $ledger = [];
        foreach (['CTC','TIC','VTC','INC','SCC','WPC','WDC','JEC','FLC','GRC','SAX'] as $cur) {
            $stmt = $udb->prepare("SELECT COALESCE(SUM(amount), 0) FROM currency_ledger WHERE currency = ?");
            $stmt->execute([$cur]);
            $ledger[$cur] = (int)$stmt->fetchColumn();
        }

        // SelfAI-X composite score
        $w = $cfg['scoring_weights'];
        $interactionsScore = min(1.0, $totalConv / 50); // 50 turns = full
        $dataRichnessScore = min(1.0, $attrCount / 8);
        $resumeScore = $resumeCount > 0 ? 1.0 : 0.0;
        $cloneMastery = min(1.0, $distinctClones / 10);
        $feedbackScore = ($fbAvg + 1) / 2; // map -1..1 to 0..1
        $composite = 100 * (
            $w['interactions']   * $interactionsScore +
            $w['data_richness']  * $dataRichnessScore +
            $w['resume_uploads'] * $resumeScore +
            $w['clone_mastery']  * $cloneMastery +
            $w['feedback']       * $feedbackScore
        );
        $selfai_x_score = round($composite, 1);

        // Recent activity (last 5 user turns + their AI reply)
        $recentRows = $udb->query("
            SELECT id, clone_id, clone_name, role, substr(content, 1, 200) AS preview, timestamp
            FROM conversations ORDER BY id DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        selfai_json([
            'ok' => true,
            'user' => $user,
            'clones' => $clones,
            'metrics' => [
                'total_conversations' => $totalConv,
                'distinct_clones'     => $distinctClones,
                'attributes'          => $attrCount,
                'resumes'             => $resumeCount,
                'feedback_avg'        => $fbAvg,
                'top_clone'           => $topRow,
            ],
            'ledger' => $ledger,
            'selfai_x_score' => $selfai_x_score,
            'recent' => $recentRows,
        ]);
    }

    if ($action === 'history') {
        $cloneId = $_GET['clone_id'] ?? '';
        if ($cloneId === '') selfai_json(['error' => 'clone_id_required'], 400);
        $stmt = $udb->prepare("SELECT id, role, content, feedback, timestamp FROM conversations WHERE clone_id = ? ORDER BY id ASC LIMIT 200");
        $stmt->execute([$cloneId]);
        selfai_json(['ok' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $body = selfai_body();

    if ($action === 'attribute') {
        $k = trim((string)($body['attribute_key'] ?? ''));
        $v = trim((string)($body['attribute_value'] ?? ''));
        if ($k === '') selfai_json(['error' => 'attribute_key_required'], 400);
        $udb->prepare("INSERT INTO data_selfai (attribute_key, attribute_value) VALUES (?, ?)
                        ON CONFLICT(attribute_key) DO UPDATE SET attribute_value = excluded.attribute_value, updated_at = CURRENT_TIMESTAMP")
            ->execute([$k, $v]);
        selfai_json(['ok' => true]);
    }

    if ($action === 'resume') {
        $name = trim((string)($body['filename'] ?? 'resume.txt'));
        $content = (string)($body['file_content'] ?? '');
        if ($content === '') selfai_json(['error' => 'file_content_required'], 400);
        $udb->prepare("INSERT INTO resumes (filename, file_content) VALUES (?, ?)")
            ->execute([$name, substr($content, 0, 200000)]);
        selfai_json(['ok' => true, 'stored_chars' => min(strlen($content), 200000)]);
    }

    if ($action === 'feedback') {
        $turnId = (int)($body['turn_id'] ?? 0);
        $fb = (int)($body['feedback'] ?? 0); // -1, 0, +1
        if ($turnId <= 0 || !in_array($fb, [-1, 0, 1], true)) selfai_json(['error' => 'invalid_feedback'], 400);
        $udb->prepare("UPDATE conversations SET feedback = ? WHERE id = ? AND role = 'assistant'")->execute([$fb, $turnId]);
        selfai_json(['ok' => true]);
    }

    selfai_json(['error' => 'unknown_action'], 400);
} catch (Throwable $e) {
    selfai_json(['error' => 'server_error', 'detail' => $e->getMessage()], 500);
}
