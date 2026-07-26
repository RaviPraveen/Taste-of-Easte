<?php
require_once __DIR__ . '/config.php';
require_admin();
$page_title = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/header.php';

$pdo = db();
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$dayStats = function (string $day) use ($pdo): array {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS sales, COALESCE(AVG(total),0) AS avg_order
         FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'"
    );
    $stmt->execute([$day]);
    return $stmt->fetch();
};
$s = $dayStats($today);
$y = $dayStats($yesterday);

$delta = function (float $now, float $before): ?float {
    if ($before <= 0) {
        return null;
    }
    return ($now - $before) / $before * 100;
};
$salesDelta = $delta((float) $s['sales'], (float) $y['sales']);
$ordersDelta = $delta((float) $s['orders'], (float) $y['orders']);

$itemsSold = $pdo->prepare(
    "SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) = ? AND o.status != 'cancelled'"
);
$itemsSold->execute([$today]);
$soldCount = (int) $itemsSold->fetchColumn();

// Last 7 days of sales for the trend chart.
$weekStmt = $pdo->prepare(
    "SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS sales, COUNT(*) AS orders
     FROM orders WHERE DATE(created_at) >= ? AND status != 'cancelled'
     GROUP BY DATE(created_at)"
);
$weekStmt->execute([date('Y-m-d', strtotime('-6 days'))]);
$rows = [];
foreach ($weekStmt->fetchAll() as $r) {
    $rows[$r['day']] = $r;
}
$chartLabels = [];
$chartSales = [];
$chartOrders = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($d));
    $chartSales[] = (float) ($rows[$d]['sales'] ?? 0);
    $chartOrders[] = (int) ($rows[$d]['orders'] ?? 0);
}

$topItems = $pdo->prepare(
    "SELECT oi.item_name, SUM(oi.qty) AS qty, SUM(oi.line_total) AS amount
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) = ? AND o.status != 'cancelled'
     GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5"
);
$topItems->execute([$today]);
$top = $topItems->fetchAll();
$topMax = max(1, ...array_map(fn ($t) => (int) $t['qty'], $top ?: [['qty' => 1]]));

$recent = $pdo->query(
    'SELECT o.*, u.name AS cashier FROM orders o LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC LIMIT 8'
)->fetchAll();
$badges = ['pending' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'];

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$currency = setting('currency', 'Rs.');
?>
<!-- Welcome -->
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <div class="welcome-title"><?= e($greeting) ?>, <?= e(current_user()['name']) ?> 👋</div>
    <div class="welcome-sub">Here's how <?= e(setting('hotel_name')) ?> is doing today.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="pos.php" class="quick-action"><i class="bi bi-plus-lg"></i>New Sale</a>
    <a href="menu.php" class="quick-action"><i class="bi bi-journal-plus"></i>Add Item</a>
    <a href="categories.php" class="quick-action"><i class="bi bi-tags"></i>Category</a>
    <a href="reports.php" class="quick-action"><i class="bi bi-bar-chart"></i>Reports</a>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card border-0 shadow-sm"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div class="stat-icon" style="background:var(--primary-soft);color:var(--primary)"><i class="bi bi-cash-stack"></i></div>
        <?php if ($salesDelta !== null): ?>
        <span class="stat-delta <?= $salesDelta >= 0 ? 'up' : 'down' ?>"><i class="bi bi-arrow-<?= $salesDelta >= 0 ? 'up' : 'down' ?>-short"></i><?= number_format(abs($salesDelta), 0) ?>%</span>
        <?php endif; ?>
      </div>
      <div class="stat-value" data-countup="<?= (float) $s['sales'] ?>" data-decimals="2" data-prefix="<?= e($currency) ?> "><?= rs($s['sales']) ?></div>
      <div class="stat-label">Today's Revenue</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card border-0 shadow-sm"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div class="stat-icon" style="background:var(--success-soft);color:#16A34A"><i class="bi bi-receipt"></i></div>
        <?php if ($ordersDelta !== null): ?>
        <span class="stat-delta <?= $ordersDelta >= 0 ? 'up' : 'down' ?>"><i class="bi bi-arrow-<?= $ordersDelta >= 0 ? 'up' : 'down' ?>-short"></i><?= number_format(abs($ordersDelta), 0) ?>%</span>
        <?php endif; ?>
      </div>
      <div class="stat-value" data-countup="<?= (int) $s['orders'] ?>"><?= (int) $s['orders'] ?></div>
      <div class="stat-label">Orders Today</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card border-0 shadow-sm"><div class="card-body">
      <div class="stat-icon" style="background:var(--warning-soft);color:#B45309"><i class="bi bi-basket"></i></div>
      <div class="stat-value" data-countup="<?= $soldCount ?>"><?= $soldCount ?></div>
      <div class="stat-label">Items Sold</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card border-0 shadow-sm"><div class="card-body">
      <div class="stat-icon" style="background:#F5F3FF;color:#7C3AED"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-value" data-countup="<?= (float) $s['avg_order'] ?>" data-decimals="2" data-prefix="<?= e($currency) ?> "><?= rs($s['avg_order']) ?></div>
      <div class="stat-label">Avg. Order Value</div>
    </div></div>
  </div>
</div>

<!-- Chart + top items -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Revenue Trend</span>
        <span class="badge text-bg-light">Last 7 days</span>
      </div>
      <div class="card-body" style="height:280px;">
        <canvas id="salesChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header">Best Sellers Today</div>
      <div class="card-body">
        <?php if (!$top): ?>
          <div class="empty-state"><i class="bi bi-basket"></i>No sales yet today.<br>
            <a href="pos.php" class="btn btn-brand btn-sm mt-3"><i class="bi bi-plus-lg"></i> Start a sale</a>
          </div>
        <?php endif; ?>
        <?php foreach ($top as $idx => $t): ?>
        <div class="<?= $idx > 0 ? 'mt-3' : '' ?>">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fw-medium" style="font-size:.84rem"><?= e($t['item_name']) ?></span>
            <span class="text-muted" style="font-size:.76rem"><?= (int) $t['qty'] ?> sold &middot; <?= rs($t['amount']) ?></span>
          </div>
          <div class="progress"><div class="progress-bar" style="width:<?= round((int) $t['qty'] / $topMax * 100) ?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent orders -->
<div class="card border-0 shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Recent Orders</span>
    <a href="orders.php" class="btn btn-outline-brand btn-sm">View all</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Order #</th><th>Time</th><th>Cashier</th><th class="text-end">Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5"><div class="empty-state"><i class="bi bi-receipt"></i>No orders yet — they'll appear here.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $o): ?>
          <tr style="cursor:pointer" onclick="window.open('receipt.php?id=<?= (int) $o['id'] ?>','_blank')">
            <td class="fw-medium"><?= e($o['order_no']) ?></td>
            <td class="text-muted"><?= e(date('d M h:i A', strtotime($o['created_at']))) ?></td>
            <td><?= e($o['cashier'] ?? '-') ?></td>
            <td class="text-end fw-medium"><?= rs($o['total']) ?></td>
            <td><span class="badge text-bg-<?= $badges[$o['status']] ?? 'secondary' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const canvas = document.getElementById('salesChart');
const g = canvas.getContext('2d').createLinearGradient(0, 0, 0, 260);
g.addColorStop(0, 'rgba(37, 99, 235, 0.22)');
g.addColorStop(1, 'rgba(37, 99, 235, 0)');
new Chart(canvas, {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode($chartSales) ?>,
      borderColor: '#2563EB',
      backgroundColor: g,
      fill: true,
      tension: 0.4,
      borderWidth: 2.5,
      pointRadius: 3,
      pointBackgroundColor: '#fff',
      pointBorderColor: '#2563EB',
      pointBorderWidth: 2,
      pointHoverRadius: 5,
    }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0F172A',
        padding: 12,
        cornerRadius: 10,
        displayColors: false,
        callbacks: {
          label: (c) => '<?= e($currency) ?> ' + c.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 }),
        },
      },
    },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(15,23,42,0.05)' }, ticks: { font: { family: 'Inter' }, color: '#6B7280' }, border: { display: false } },
      x: { grid: { display: false }, ticks: { font: { family: 'Inter' }, color: '#6B7280' }, border: { display: false } },
    },
  },
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
