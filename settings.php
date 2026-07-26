<?php
require_once __DIR__ . '/config.php';
require_admin();

const LOGO_DIR = __DIR__ . '/assets/uploads';
const LOGO_URL = 'assets/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $save = [
        'hotel_name'         => trim($_POST['hotel_name'] ?? '') ?: setting('hotel_name'),
        'currency'           => trim($_POST['currency'] ?? '') ?: 'Rs.',
        'service_charge_pct' => (string) max(0, min(100, (float) ($_POST['service_charge_pct'] ?? 0))),
    ];

    // Hotel logo: shown in the sidebar, login page and printed bills.
    $oldLogo = setting('logo_img', '');
    if (isset($_POST['remove_logo_img'])) {
        if ($oldLogo && is_file(__DIR__ . '/' . $oldLogo)) {
            @unlink(__DIR__ . '/' . $oldLogo);
        }
        $save['logo_img'] = '';
    } elseif (!empty($_FILES['logo_img']['name']) && $_FILES['logo_img']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_img']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            flash('Logo must be a JPG, PNG, WEBP or GIF image.', 'danger');
            header('Location: settings.php');
            exit;
        }
        if ($_FILES['logo_img']['size'] > 2 * 1024 * 1024) {
            flash('Logo image must be under 2 MB.', 'danger');
            header('Location: settings.php');
            exit;
        }
        if (!is_dir(LOGO_DIR)) {
            mkdir(LOGO_DIR, 0777, true);
        }
        $file = 'logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo_img']['tmp_name'], LOGO_DIR . '/' . $file)) {
            if ($oldLogo && is_file(__DIR__ . '/' . $oldLogo)) {
                @unlink(__DIR__ . '/' . $oldLogo);
            }
            $save['logo_img'] = LOGO_URL . '/' . $file;
        }
    }

    $stmt = db()->prepare('INSERT INTO settings (name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($save as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    flash('Settings saved.');
    header('Location: settings.php');
    exit;
}

$page_title = 'Settings';
$active = 'settings';
require_once __DIR__ . '/header.php';
$logoImg = setting('logo_img', '');
?>
<div class="text-muted small mb-3">Hotel identity &amp; billing configuration &middot; Address, phone, tagline and footer message are managed in the <a href="receipt_designer.php">Bill Designer</a>.</div>
<div class="row">
  <div class="col-lg-6">
    <form method="post" enctype="multipart/form-data">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-shop me-1"></i> Hotel Identity</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-medium">Hotel name <span class="text-muted fw-normal">(shown on sidebar, login, dashboard &amp; bills)</span></label>
            <input type="text" name="hotel_name" class="form-control" value="<?= e(setting('hotel_name')) ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-medium">Hotel logo <span class="text-muted fw-normal">(shown everywhere, PNG / JPG, max 2 MB)</span></label>
            <div class="d-flex align-items-center gap-3 mb-2">
              <?php if ($logoImg): ?>
                <img src="<?= e($logoImg) ?>" class="thumb" alt="Current logo">
                <span class="small text-muted">Current logo</span>
              <?php else: ?>
                <span class="thumb-ph"><i class="bi bi-shop text-muted"></i></span>
                <span class="small text-muted">No logo uploaded — default icon is shown.</span>
              <?php endif; ?>
            </div>
            <input type="file" name="logo_img" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
            <?php if ($logoImg): ?>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="remove_logo_img" id="removeLogo">
              <label class="form-check-label small" for="removeLogo">Remove current logo</label>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Billing</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label small fw-medium">Currency symbol</label>
              <input type="text" name="currency" class="form-control" value="<?= e(setting('currency')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-medium">Service charge %</label>
              <input type="number" name="service_charge_pct" class="form-control" min="0" max="100" step="0.5" value="<?= e(setting('service_charge_pct')) ?>">
              <div class="form-text">Added to every bill (cashier can untick it per order). Set 0 to disable.</div>
            </div>
          </div>
        </div>
      </div>

      <button class="btn btn-brand fw-medium"><i class="bi bi-check2"></i> Save Settings</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
