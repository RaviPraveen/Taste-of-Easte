<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['items']) || !is_array($input['items'])) {
    echo json_encode(['ok' => false, 'error' => 'No items in order.']);
    exit;
}

$orderType = in_array($input['order_type'] ?? '', ['dine_in', 'takeaway'], true)
    ? $input['order_type'] : 'takeaway';
$tableNo = $orderType === 'dine_in' ? trim((string) ($input['table_no'] ?? '')) : '';
$method = ($input['payment_method'] ?? '') === 'card' ? 'card' : 'cash';
$discount = max(0.0, (float) ($input['discount'] ?? 0));
$useService = !empty($input['service']);
$paid = max(0.0, (float) ($input['paid'] ?? 0));

$pdo = db();
try {
    // Recompute all prices from the database — never trust client totals.
    $ids = array_values(array_unique(array_map(fn ($i) => (int) ($i['id'] ?? 0), $input['items'])));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM menu_items WHERE available = 1 AND id IN ($placeholders)");
    $stmt->execute($ids);
    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $menu[(int) $row['id']] = $row;
    }

    $lines = [];
    $subtotal = 0.0;
    foreach ($input['items'] as $line) {
        $id = (int) ($line['id'] ?? 0);
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty < 1 || !isset($menu[$id])) {
            continue;
        }
        $price = (float) $menu[$id]['price'];
        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;
        $lines[] = [$menu[$id]['name'], $price, $qty, $lineTotal];
    }
    if (!$lines) {
        echo json_encode(['ok' => false, 'error' => 'No valid items in order.']);
        exit;
    }

    $service = $useService ? round($subtotal * (float) setting('service_charge_pct', '10') / 100, 2) : 0.0;
    $discount = min($discount, $subtotal + $service);
    $total = round($subtotal + $service - $discount, 2);

    if ($method === 'card') {
        $paid = $total;
    } elseif ($paid < $total) {
        echo json_encode(['ok' => false, 'error' => 'Cash received is less than the total.']);
        exit;
    }
    $change = round($paid - $total, 2);

    // Takeaway: billed before the food is handed over, so it stays pending until
    // staff marks it completed. Dine-in: billed after the meal, so it's already done.
    $status = $orderType === 'takeaway' ? 'pending' : 'completed';

    $pdo->beginTransaction();
    $orderNo = next_order_no($pdo);
    $pdo->prepare(
        'INSERT INTO orders (order_no, order_type, table_no, subtotal, service_charge, discount, total,
            payment_method, paid, change_due, status, user_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $orderNo, $orderType, $tableNo ?: null, $subtotal, $service, $discount, $total,
        $method, $paid, $change, $status, current_user()['id'],
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, item_name, price, qty, line_total) VALUES (?,?,?,?,?)');
    foreach ($lines as [$name, $price, $qty, $lineTotal]) {
        $itemStmt->execute([$orderId, $name, $price, $qty, $lineTotal]);
    }
    $pdo->commit();

    echo json_encode(['ok' => true, 'id' => $orderId, 'order_no' => $orderNo]);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $ex->getMessage()]);
}
