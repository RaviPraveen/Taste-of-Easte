<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$u = current_user();
echo json_encode(['id' => (int) ($u['id'] ?? 0), 'role' => $u['role'] ?? '']);
