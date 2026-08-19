<?php
/**
 * bellbored notification API.
 *
 * Exposes unread-count retrieval and mark-as-read for the current user.
 * No authentication beyond the existing session is performed: the caller
 * must be logged in (the bell only renders for authenticated users).
 */

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/setup.php';

header('Content-Type: application/json; charset=utf-8');

// Always respond with JSON, even on fatal errors, so the frontend never
// receives an HTML error page that would break JSON.parse in production.
set_exception_handler(function ($e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Internal error: ' . $e->getMessage()]);
    exit;
});

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$driver = $config['db_driver'] ?? 'sqlite';

$verb = $_SERVER['REQUEST_METHOD'];

if ($verb === 'GET') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    $listStmt = $pdo->prepare("SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $listStmt->execute([$userId]);
    $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['count' => $count, 'items' => $items]);
    exit;
}

if ($verb === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($data['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_csrf']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
