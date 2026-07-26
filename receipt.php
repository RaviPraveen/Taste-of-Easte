<?php
require_once __DIR__ . '/config.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT o.*, u.name AS cashier FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    exit('Order not found.');
}
$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();
$typeLabels = ['dine_in' => 'Dine-In', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];

// Bill Designer settings
$on = fn (string $k) => setting($k, '1') === '1';
$widthPx = setting('rc_width', '80') === '58' ? 220 : 300;
$fontPx = (int) setting('rc_font', '12');
$divider = setting('rc_divider', 'dashed');
$dividerCss = ['dashed' => '1px dashed #000', 'solid' => '1px solid #000', 'double' => '3px double #000'][$divider] ?? '1px dashed #000';
$tagline = setting('rc_tagline', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= e($order['order_no']) ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Courier New', monospace; font-size: <?= $fontPx ?>px; color: #000; background: #f0f0f0; }
  .receipt { width: <?= $widthPx ?>px; margin: 20px auto; background: #fff; padding: 16px 14px; }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .hr { border-top: <?= $dividerCss ?>; margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 1px 0; vertical-align: top; }
  .r { text-align: right; }
  .toolbar { text-align: center; margin: 12px; }
  .toolbar button { padding: 8px 16px; font-family: inherit; cursor: pointer; }
  @media print {
    body { background: #fff; }
    .receipt { margin: 0; width: 100%; }
    .toolbar { display: none; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">🖨 Print</button>
  <button onclick="window.close()">Close</button>
</div>
<div class="receipt">
  <?php if ($on('rc_show_logo') && setting('logo_img')): ?>
    <div class="center"><img src="<?= e(setting('logo_img')) ?>" alt="logo" style="max-width:<?= (int) setting('rc_logo_size', '55') ?>%;"></div>
  <?php endif; ?>
  <div class="center bold" style="font-size:1.25em;"><?= e(setting('hotel_name')) ?></div>
  <?php if ($tagline !== ''): ?><div class="center"><?= e($tagline) ?></div><?php endif; ?>
  <?php if ($on('rc_show_address')): ?><div class="center"><?= e(setting('address')) ?></div><?php endif; ?>
  <?php if ($on('rc_show_phone')): ?><div class="center">Tel: <?= e(setting('phone')) ?></div><?php endif; ?>
  <div class="hr"></div>
  <table>
    <tr><td>Receipt</td><td class="r"><?= e($order['order_no']) ?></td></tr>
    <tr><td>Date</td><td class="r"><?= e(date('Y-m-d H:i', strtotime($order['created_at']))) ?></td></tr>
    <?php if ($on('rc_show_cashier')): ?>
    <tr><td>Cashier</td><td class="r"><?= e($order['cashier'] ?? '-') ?></td></tr>
    <?php endif; ?>
    <?php if ($on('rc_show_type')): ?>
    <tr><td>Type</td><td class="r"><?= e($typeLabels[$order['order_type']] ?? $order['order_type']) ?><?= $order['table_no'] ? ' (Table ' . e($order['table_no']) . ')' : '' ?></td></tr>
    <?php endif; ?>
  </table>
  <div class="hr"></div>
  <table>
    <tr class="bold"><td>Item</td><td class="r">Qty</td><td class="r">Amount</td></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= e($it['item_name']) ?><?php if ($on('rc_show_unit')): ?><br><span style="font-size:0.8em;">@ <?= number_format((float) $it['price'], 2) ?></span><?php endif; ?></td>
      <td class="r"><?= (int) $it['qty'] ?></td>
      <td class="r"><?= number_format((float) $it['line_total'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="hr"></div>
  <table>
    <tr><td>Subtotal</td><td class="r"><?= rs($order['subtotal']) ?></td></tr>
    <?php if ((float) $order['service_charge'] > 0): ?>
    <tr><td>Service charge</td><td class="r"><?= rs($order['service_charge']) ?></td></tr>
    <?php endif; ?>
    <?php if ((float) $order['discount'] > 0): ?>
    <tr><td>Discount</td><td class="r">-<?= rs($order['discount']) ?></td></tr>
    <?php endif; ?>
    <tr class="bold" <?= $on('rc_bold_total') ? 'style="font-size:1.15em;"' : '' ?>><td>TOTAL</td><td class="r"><?= rs($order['total']) ?></td></tr>
    <?php if ($on('rc_show_change')): ?>
    <tr><td>Paid (<?= e(ucfirst($order['payment_method'])) ?>)</td><td class="r"><?= rs($order['paid']) ?></td></tr>
    <tr><td>Change</td><td class="r"><?= rs($order['change_due']) ?></td></tr>
    <?php endif; ?>
  </table>
  <div class="hr"></div>
  <div class="center"><?= e(setting('receipt_footer')) ?></div>
</div>
<?php if (!empty($_GET['autoprint'])): ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
<?php endif; ?>
</body>
</html>
