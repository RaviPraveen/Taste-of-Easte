<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$status = $input['status'] ?? '';

if (!$id || !in_array($status, ['completed', 'cancelled'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$stmt = db()->prepare("UPDATE orders SET status = ? WHERE id = ? AND status != 'cancelled'");
$stmt->execute([$status, $id]);
echo json_encode(['ok' => $stmt->rowCount() > 0]);
