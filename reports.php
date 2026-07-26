<?php
require_once __DIR__ . '/config.php';
require_admin();
$page_title = 'Reports';
$active = 'reports';
require_once __DIR__ . '/header.php';

$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d');
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$pdo = db();

$sum = $pdo->prepare(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(subtotal),0) AS subtotal,
            COALESCE(SUM(service_charge),0) AS service, COALESCE(SUM(discount),0) AS discount,
            COALESCE(SUM(total),0) AS total
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'"
);
$sum->execute([$from, $to]);
$s = $sum->fetch();

$byPayment = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
     GROUP BY payment_method"
);
$byPayment->execute([$from, $to]);
$payments = $byPayment->fetchAll();

$byType = $pdo->prepare(
    "SELECT order_type, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
     GROUP BY order_type"
);
$byType->execute([$from, $to]);
$types = $byType->fetchAll();

$topItems = $pdo->prepare(
    "SELECT oi.item_name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS amount
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
     GROUP BY oi.item_name ORDER BY qty DESC LIMIT 10"
);
$topItems->execute([$from, $to]);
$top = $topItems->fetchAll();

$daily = $pdo->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(total),0) AS sales
     FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
     GROUP BY DATE(created_at) ORDER BY day DESC"
);
$daily->execute([$from, $to]);
$days = $daily->fetchAll();

$typeLabels = ['dine_in' => 'Dine-In', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div class="text-muted small">Sales summary for the selected date range</div>
  <form class="d-flex gap-2 align-items-center" method="get">
    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
    <span class="text-muted small">to</span>
    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
    <button class="btn btn-sm btn-brand">Apply</button>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body">
    <div class="stat-value"><?= rs($s['total']) ?></div><div class="stat-label">Net Sales</div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body">
    <div class="stat-value"><?= (int) $s['orders'] ?></div><div class="stat-label">Orders</div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body">
    <div class="stat-value"><?= rs($s['service']) ?></div><div class="stat-label">Service Charges</div>
  </div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body">
    <div class="stat-value text-danger"><?= rs($s['discount']) ?></div><div class="stat-label">Discounts Given</div>
  </div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold">By Payment Method</div>
      <div class="card-body p-0">
        <table class="table mb-0 small align-middle">
          <tbody>
          <?php if (!$payments): ?><tr><td class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><i class="bi bi-<?= $p['payment_method'] === 'cash' ? 'cash' : 'credit-card' ?>"></i> <?= e(ucfirst($p['payment_method'])) ?></td>
              <td class="text-center"><span class="badge text-bg-light border"><?= (int) $p['cnt'] ?></span></td>
              <td class="text-end fw-semibold"><?= rs($p['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">By Order Type</div>
      <div class="card-body p-0">
        <table class="table mb-0 small align-middle">
          <tbody>
          <?php if (!$types): ?><tr><td class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          <?php foreach ($types as $t): ?>
            <tr>
              <td><?= e($typeLabels[$t['order_type']] ?? $t['order_type']) ?></td>
              <td class="text-center"><span class="badge text-bg-light border"><?= (int) $t['cnt'] ?></span></td>
              <td class="text-end fw-semibold"><?= rs($t['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-bold">Top Selling Items</div>
      <div class="card-body p-0">
        <table class="table mb-0 small align-middle">
          <thead class="table-light"><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
          <?php if (!$top): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          <?php foreach ($top as $t): ?>
            <tr>
              <td><?= e($t['item_name']) ?></td>
              <td class="text-center"><span class="badge text-bg-light border"><?= (int) $t['qty'] ?></span></td>
              <td class="text-end"><?= rs($t['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-bold">Daily Breakdown</div>
      <div class="card-body p-0">
        <table class="table mb-0 small align-middle">
          <thead class="table-light"><tr><th>Date</th><th class="text-center">Orders</th><th class="text-end">Sales</th></tr></thead>
          <tbody>
          <?php if (!$days): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          <?php foreach ($days as $d): ?>
            <tr>
              <td><?= e(date('d M Y', strtotime($d['day']))) ?></td>
              <td class="text-center"><span class="badge text-bg-light border"><?= (int) $d['orders'] ?></span></td>
              <td class="text-end fw-semibold"><?= rs($d['sales']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
