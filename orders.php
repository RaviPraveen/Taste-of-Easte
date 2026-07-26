<?php
$page_title = 'Orders';
$active = 'orders';
require_once __DIR__ . '/header.php';

$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$validStatuses = ['pending', 'completed', 'cancelled'];

$sql = 'SELECT o.*, u.name AS cashier FROM orders o LEFT JOIN users u ON u.id = o.user_id
        WHERE DATE(o.created_at) = ?';
$params = [$date];
if (in_array($status, $validStatuses, true)) {
    $sql .= ' AND o.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY o.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$badges = ['pending' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'];
$typeLabels = ['dine_in' => 'Dine-In', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
$dayTotal = array_sum(array_map(
    fn ($o) => $o['status'] !== 'cancelled' ? (float) $o['total'] : 0,
    $orders
));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div class="text-muted small"><?= count($orders) ?> order(s) &middot; Net total <span class="fw-bold text-brand"><?= rs($dayTotal) ?></span></div>
  <form class="d-flex gap-2" method="get">
    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
    <select name="status" class="form-select form-select-sm" style="width:auto">
      <option value="">All statuses</option>
      <?php foreach ($validStatuses as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-brand">Filter</button>
  </form>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Order #</th><th>Time</th><th>Type</th><th>Cashier</th>
            <th class="text-end">Total</th><th>Payment</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <i class="bi bi-inbox"></i>No orders for this date.<br>
              <a href="pos.php" class="btn btn-brand btn-sm mt-3"><i class="bi bi-plus-lg"></i> Start a sale</a>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?= e($o['order_no']) ?></td>
            <td><?= e(date('h:i A', strtotime($o['created_at']))) ?></td>
            <td><?= e($typeLabels[$o['order_type']]) ?><?= $o['table_no'] ? ' (T' . e($o['table_no']) . ')' : '' ?></td>
            <td><?= e($o['cashier'] ?? '-') ?></td>
            <td class="text-end fw-semibold"><?= rs($o['total']) ?></td>
            <td><span class="badge text-bg-light border"><i class="bi bi-<?= $o['payment_method'] === 'cash' ? 'cash' : 'credit-card' ?>"></i> <?= e(ucfirst($o['payment_method'])) ?></span></td>
            <td><span class="badge text-bg-<?= $badges[$o['status']] ?? 'secondary' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="receipt.php?id=<?= (int) $o['id'] ?>" target="_blank" title="Print bill"><i class="bi bi-printer"></i></a>
              <?php if ($o['status'] === 'pending'): ?>
                <button class="btn btn-sm btn-success btn-pulse" onclick="setStatus(<?= (int) $o['id'] ?>,'completed')" title="Mark completed"><i class="bi bi-check2"></i> Complete</button>
              <?php endif; ?>
              <?php if ($o['status'] !== 'cancelled'): ?>
                <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Cancel this order? It will be excluded from sales.'))setStatus(<?= (int) $o['id'] ?>,'cancelled')" title="Cancel"><i class="bi bi-x-lg"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
async function setStatus(id, status) {
  await fetch('api/update_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, status }),
  });
  location.reload();
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
