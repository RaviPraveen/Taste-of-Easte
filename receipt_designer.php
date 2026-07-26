<?php
require_once __DIR__ . '/config.php';
require_admin();

$toggles = ['rc_show_logo', 'rc_show_address', 'rc_show_phone', 'rc_show_cashier',
            'rc_show_type', 'rc_show_unit', 'rc_show_change', 'rc_bold_total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $save = [
        'rc_width'   => in_array($_POST['rc_width'] ?? '', ['58', '80'], true) ? $_POST['rc_width'] : '80',
        'rc_font'    => (string) max(9, min(16, (int) ($_POST['rc_font'] ?? 12))),
        'rc_tagline' => trim($_POST['rc_tagline'] ?? ''),
        'rc_divider' => in_array($_POST['rc_divider'] ?? '', ['dashed', 'solid', 'double'], true) ? $_POST['rc_divider'] : 'dashed',
        'rc_logo_size' => (string) max(20, min(100, (int) ($_POST['rc_logo_size'] ?? 55))),
        'address'        => trim($_POST['address'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
    ];
    foreach ($toggles as $t) {
        $save[$t] = isset($_POST[$t]) ? '1' : '0';
    }
    $stmt = db()->prepare('INSERT INTO settings (name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($save as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    flash('Bill design saved. All new receipts will use it.');
    header('Location: receipt_designer.php');
    exit;
}

$page_title = 'Bill Designer';
$active = 'designer';
require_once __DIR__ . '/header.php';

$rc = [
    'width'   => setting('rc_width', '80'),
    'font'    => setting('rc_font', '12'),
    'tagline' => setting('rc_tagline', ''),
    'divider' => setting('rc_divider', 'dashed'),
];
$logoImg = setting('logo_img', '');
$on = fn (string $k) => setting($k, '1') === '1';
$sampleDate = date('Y-m-d h:i A');
?>
<div class="text-muted small mb-3">Hotel name and logo come from <a href="settings.php">Settings</a> — here you design how the printed bill looks.</div>
<div class="row g-3">
  <!-- Controls -->
  <div class="col-lg-5">
    <form method="post" id="designerForm">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-shop me-1"></i> Bill Details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-medium">Address</label>
              <input type="text" class="form-control form-control-sm" name="address" id="dAddress" value="<?= e(setting('address')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-medium">Phone</label>
              <input type="text" class="form-control form-control-sm" name="phone" id="dPhone" value="<?= e(setting('phone')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-medium">Tagline <span class="text-muted fw-normal">(under hotel name)</span></label>
              <input type="text" class="form-control form-control-sm" name="rc_tagline" id="dTagline" value="<?= e($rc['tagline']) ?>" placeholder="e.g. Authentic Sri Lankan Taste">
            </div>
            <div class="col-12">
              <label class="form-label small fw-medium">Receipt footer message</label>
              <input type="text" class="form-control form-control-sm" name="receipt_footer" id="dFooter" value="<?= e(setting('receipt_footer')) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-sliders me-1"></i> Paper &amp; Text</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-4">
              <label class="form-label small fw-medium">Paper width</label>
              <select class="form-select form-select-sm" name="rc_width" id="dWidth">
                <option value="80" <?= $rc['width'] === '80' ? 'selected' : '' ?>>80 mm</option>
                <option value="58" <?= $rc['width'] === '58' ? 'selected' : '' ?>>58 mm</option>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">Font: <span id="dFontVal"><?= e($rc['font']) ?></span>px</label>
              <input type="range" class="form-range" name="rc_font" id="dFont" min="9" max="16" value="<?= e($rc['font']) ?>">
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">Dividers</label>
              <select class="form-select form-select-sm" name="rc_divider" id="dDivider">
                <option value="dashed" <?= $rc['divider'] === 'dashed' ? 'selected' : '' ?>>Dashed</option>
                <option value="solid" <?= $rc['divider'] === 'solid' ? 'selected' : '' ?>>Solid</option>
                <option value="double" <?= $rc['divider'] === 'double' ? 'selected' : '' ?>>Double</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-medium">Logo size: <span id="dLogoSizeVal"><?= e(setting('rc_logo_size', '55')) ?></span>%</label>
              <input type="range" class="form-range" name="rc_logo_size" id="dLogoSize" min="20" max="100" value="<?= e(setting('rc_logo_size', '55')) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-toggles me-1"></i> Show / Hide Elements</div>
        <div class="card-body">
          <div class="row g-2">
            <?php
            $labels = [
                'rc_show_logo'    => 'Logo',
                'rc_show_address' => 'Address',
                'rc_show_phone'   => 'Phone number',
                'rc_show_cashier' => 'Cashier name',
                'rc_show_type'    => 'Order type / table',
                'rc_show_unit'    => 'Unit prices',
                'rc_show_change'  => 'Paid & change rows',
                'rc_bold_total'   => 'Large bold total',
            ];
            foreach ($labels as $key => $label): ?>
            <div class="col-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="t_<?= $key ?>" <?= $on($key) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="t_<?= $key ?>"><?= e($label) ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-brand fw-medium"><i class="bi bi-check2"></i> Save Design</button>
        <a href="receipt_designer.php" class="btn btn-outline-secondary">Reset changes</a>
      </div>
    </form>
  </div>

  <!-- Live preview -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-eye me-1"></i> Live Preview</span>
        <span class="badge text-bg-light border fw-normal">updates as you type</span>
      </div>
      <div class="card-body designer-preview-wrap">
        <div class="bill" id="pv" style="width:300px;font-size:12px;">
          <div class="center" id="pvLogoWrap" style="<?= $logoImg ? '' : 'display:none;' ?>">
            <img id="pvLogoImg" src="<?= e($logoImg) ?>" alt="" style="max-width:<?= (int) setting('rc_logo_size', '55') ?>%;">
          </div>
          <div class="center bold" style="font-size:1.25em;"><?= e(setting('hotel_name')) ?></div>
          <div class="center" id="pvTagline" style="<?= $rc['tagline'] === '' ? 'display:none' : '' ?>"><?= e($rc['tagline']) ?></div>
          <div class="center" id="pvAddress"><?= e(setting('address')) ?></div>
          <div class="center" id="pvPhone">Tel: <?= e(setting('phone')) ?></div>
          <div class="hr"></div>
          <table>
            <tr><td>Receipt</td><td class="r">FH<?= date('ymd') ?>-0042</td></tr>
            <tr><td>Date</td><td class="r"><?= e($sampleDate) ?></td></tr>
            <tr id="pvCashier"><td>Cashier</td><td class="r"><?= e(current_user()['name']) ?></td></tr>
            <tr id="pvType"><td>Type</td><td class="r">Dine-In (Table 5)</td></tr>
          </table>
          <div class="hr"></div>
          <table>
            <tr class="bold"><td>Item</td><td class="r">Qty</td><td class="r">Amount</td></tr>
            <tr><td>Chicken Kottu<br><span class="pv-unit" style="font-size:0.8em;">@ 750.00</span></td><td class="r">1</td><td class="r">750.00</td></tr>
            <tr><td>Egg Hopper<br><span class="pv-unit" style="font-size:0.8em;">@ 100.00</span></td><td class="r">3</td><td class="r">300.00</td></tr>
            <tr><td>Milk Tea<br><span class="pv-unit" style="font-size:0.8em;">@ 120.00</span></td><td class="r">2</td><td class="r">240.00</td></tr>
          </table>
          <div class="hr"></div>
          <table>
            <tr><td>Subtotal</td><td class="r"><?= e(setting('currency')) ?> 1,290.00</td></tr>
            <tr><td>Service charge</td><td class="r"><?= e(setting('currency')) ?> 129.00</td></tr>
            <tr id="pvTotal" class="bold" style="font-size:1.15em;"><td>TOTAL</td><td class="r"><?= e(setting('currency')) ?> 1,419.00</td></tr>
            <tr class="pv-change"><td>Paid (Cash)</td><td class="r"><?= e(setting('currency')) ?> 1,500.00</td></tr>
            <tr class="pv-change"><td>Change</td><td class="r"><?= e(setting('currency')) ?> 81.00</td></tr>
          </table>
          <div class="hr"></div>
          <div class="center" id="pvFooter"><?= e(setting('receipt_footer')) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const $ = (id) => document.getElementById(id);
const HAS_LOGO_IMG = <?= json_encode($logoImg !== '') ?>;

function updatePreview() {
  const pv = $('pv');
  pv.style.width = ($('dWidth').value === '58' ? 220 : 300) + 'px';
  pv.style.fontSize = $('dFont').value + 'px';
  $('dFontVal').textContent = $('dFont').value;
  pv.classList.remove('div-solid', 'div-double');
  if ($('dDivider').value === 'solid') pv.classList.add('div-solid');
  if ($('dDivider').value === 'double') pv.classList.add('div-double');

  $('pvAddress').textContent = $('dAddress').value;
  $('pvPhone').textContent = 'Tel: ' + $('dPhone').value;
  $('pvTagline').textContent = $('dTagline').value;
  $('pvTagline').style.display = $('dTagline').value ? '' : 'none';
  $('pvFooter').textContent = $('dFooter').value;

  $('pvLogoWrap').style.display = ($('t_rc_show_logo').checked && HAS_LOGO_IMG) ? '' : 'none';
  $('pvLogoImg').style.maxWidth = $('dLogoSize').value + '%';
  $('dLogoSizeVal').textContent = $('dLogoSize').value;
  const show = (id, key) => { $(id).style.display = $('t_' + key).checked ? '' : 'none'; };
  show('pvAddress', 'rc_show_address');
  show('pvPhone', 'rc_show_phone');
  show('pvCashier', 'rc_show_cashier');
  show('pvType', 'rc_show_type');
  document.querySelectorAll('.pv-unit').forEach((el) => {
    el.style.display = $('t_rc_show_unit').checked ? '' : 'none';
  });
  document.querySelectorAll('.pv-change').forEach((el) => {
    el.style.display = $('t_rc_show_change').checked ? '' : 'none';
  });
  const total = $('pvTotal');
  total.style.fontSize = $('t_rc_bold_total').checked ? '1.15em' : '1em';
  total.style.fontWeight = $('t_rc_bold_total').checked ? 'bold' : 'normal';
}
document.querySelectorAll('#designerForm input, #designerForm select').forEach((el) => {
  el.addEventListener('input', updatePreview);
  el.addEventListener('change', updatePreview);
});
updatePreview();
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
