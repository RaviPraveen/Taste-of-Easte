<?php
require_once __DIR__ . '/config.php';
require_admin();

const UPLOAD_DIR = __DIR__ . '/assets/uploads';
const UPLOAD_URL = 'assets/uploads';

function handle_image_upload(): ?string
{
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        flash('Only JPG, PNG, WEBP or GIF images are allowed.', 'danger');
        return null;
    }
    if ($_FILES['image']['size'] > 3 * 1024 * 1024) {
        flash('Image must be under 3 MB.', 'danger');
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    $file = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . '/' . $file)) {
        return null;
    }
    return UPLOAD_URL . '/' . $file;
}

function delete_image(?string $path): void
{
    if ($path && str_starts_with($path, UPLOAD_URL . '/') && is_file(__DIR__ . '/' . $path)) {
        @unlink(__DIR__ . '/' . $path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $catId = (int) ($_POST['category_id'] ?? 0);
        $price = max(0, (float) ($_POST['price'] ?? 0));
        if ($name === '' || !$catId) {
            flash('Name and category are required.', 'danger');
        } else {
            $newImage = handle_image_upload();
            if ($id) {
                if ($newImage) {
                    $old = db()->prepare('SELECT image FROM menu_items WHERE id = ?');
                    $old->execute([$id]);
                    delete_image($old->fetchColumn() ?: null);
                    db()->prepare('UPDATE menu_items SET name=?, category_id=?, price=?, image=? WHERE id=?')
                        ->execute([$name, $catId, $price, $newImage, $id]);
                } else {
                    db()->prepare('UPDATE menu_items SET name=?, category_id=?, price=? WHERE id=?')
                        ->execute([$name, $catId, $price, $id]);
                }
                flash('Item updated.');
            } else {
                db()->prepare('INSERT INTO menu_items (name, category_id, price, image) VALUES (?,?,?,?)')
                    ->execute([$name, $catId, $price, $newImage]);
                flash('Item added.');
            }
        }
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE menu_items SET available = 1 - available WHERE id = ?')
            ->execute([(int) $_POST['id']]);
    } elseif ($action === 'remove_image') {
        $id = (int) $_POST['id'];
        $old = db()->prepare('SELECT image FROM menu_items WHERE id = ?');
        $old->execute([$id]);
        delete_image($old->fetchColumn() ?: null);
        db()->prepare('UPDATE menu_items SET image = NULL WHERE id = ?')->execute([$id]);
        flash('Image removed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        $old = db()->prepare('SELECT image FROM menu_items WHERE id = ?');
        $old->execute([$id]);
        delete_image($old->fetchColumn() ?: null);
        db()->prepare('DELETE FROM menu_items WHERE id = ?')->execute([$id]);
        flash('Item deleted.');
    } elseif ($action === 'bulk_availability') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $available = (int) ($_POST['available'] ?? 1) === 1 ? 1 : 0;
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            db()->prepare("UPDATE menu_items SET available = ? WHERE id IN ($ph)")
                ->execute(array_merge([$available], $ids));
            flash(count($ids) . ' item(s) marked ' . ($available ? 'available' : 'unavailable') . '.');
        }
    } elseif ($action === 'bulk_delete') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $imgs = db()->prepare("SELECT image FROM menu_items WHERE id IN ($ph)");
            $imgs->execute($ids);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $img) {
                delete_image($img ?: null);
            }
            db()->prepare("DELETE FROM menu_items WHERE id IN ($ph)")->execute($ids);
            flash(count($ids) . ' item(s) deleted.');
        }
    }
    header('Location: menu.php' . (isset($_GET['cat']) ? '?cat=' . (int) $_GET['cat'] : ''));
    exit;
}

$page_title = 'Menu Items';
$active = 'menu';
require_once __DIR__ . '/header.php';

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$catFilter = (int) ($_GET['cat'] ?? 0);
$sql = 'SELECT mi.*, c.name AS category FROM menu_items mi JOIN categories c ON c.id = mi.category_id';
$params = [];
if ($catFilter) {
    $sql .= ' WHERE mi.category_id = ?';
    $params[] = $catFilter;
}
$sql .= ' ORDER BY c.sort_order, mi.name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div class="d-flex align-items-center flex-wrap gap-2">
    <span class="text-muted small" id="bulkIdleText"><?= count($items) ?> item(s) &middot; Photos appear on the POS billing screen</span>
    <div id="bulkBar" class="d-flex align-items-center flex-wrap gap-2" style="display:none!important">
      <span class="badge text-bg-info"><span id="bulkCount">0</span> selected</span>
      <button class="btn btn-sm btn-success" onclick="bulkAvailability(1)">
        <i class="bi bi-check-circle"></i> Set Available
      </button>
      <button class="btn btn-sm btn-outline-secondary" onclick="bulkAvailability(0)">
        <i class="bi bi-slash-circle"></i> Set Unavailable
      </button>
      <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
        <i class="bi bi-trash"></i> Delete
      </button>
      <button class="btn btn-sm btn-link text-muted text-decoration-none" onclick="clearSelection()">Clear</button>
    </div>
  </div>
  <div class="d-flex gap-2">
    <form method="get">
      <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <button class="btn btn-sm btn-brand" onclick="openItem()"><i class="bi bi-plus-lg"></i> Add Item</button>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px"><input type="checkbox" class="form-check-input" id="checkAll" onclick="toggleAll(this)" aria-label="Select all items"></th>
            <th style="width:70px">Photo</th><th>Item</th><th>Category</th><th class="text-end">Price</th><th class="text-center">Available</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No items yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $it): ?>
          <tr class="<?= $it['available'] ? '' : 'table-secondary opacity-75' ?>">
            <td><input type="checkbox" class="form-check-input row-check" value="<?= (int) $it['id'] ?>" onchange="updateBulkBar()" aria-label="Select <?= e($it['name']) ?>"></td>
            <td>
              <?php if ($it['image']): ?>
                <img src="<?= e($it['image']) ?>" class="thumb" alt="">
              <?php else: ?>
                <span class="thumb-ph"><?= food_emoji($it['name'], $it['category']) ?></span>
              <?php endif; ?>
            </td>
            <td class="fw-semibold"><?= e($it['name']) ?></td>
            <td><span class="badge text-bg-light border"><?= e($it['category']) ?></span></td>
            <td class="text-end fw-semibold"><?= rs($it['price']) ?></td>
            <td class="text-center">
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                <div class="form-check form-switch d-inline-block m-0">
                  <input class="form-check-input" type="checkbox" role="switch" <?= $it['available'] ? 'checked' : '' ?>
                    onchange="this.form.submit()" aria-label="Toggle availability of <?= e($it['name']) ?>">
                </div>
              </form>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary"
                onclick='openItem(<?= json_encode(['id' => (int) $it['id'], 'name' => $it['name'], 'category_id' => (int) $it['category_id'], 'price' => (float) $it['price'], 'image' => $it['image']]) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <?php if ($it['image']): ?>
              <form method="post" class="d-inline" onsubmit="return confirmSubmit(this, 'Remove the photo of <?= e($it['name']) ?>?', 'Remove')">
                <input type="hidden" name="action" value="remove_image">
                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                <button class="btn btn-sm btn-outline-warning" title="Remove photo"><i class="bi bi-image"></i></button>
              </form>
              <?php endif; ?>
              <form method="post" class="d-inline" onsubmit="return confirmSubmit(this, 'Delete <?= e($it['name']) ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="fId">
      <div class="modal-header">
        <h5 class="modal-title" id="itemModalTitle">Add Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Item name</label>
          <input type="text" name="name" id="fName" class="form-control" required>
        </div>
        <div class="row g-2">
          <div class="col-7 mb-3">
            <label class="form-label small fw-semibold">Category</label>
            <select name="category_id" id="fCat" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-5 mb-3">
            <label class="form-label small fw-semibold">Price (<?= e(setting('currency')) ?>)</label>
            <input type="number" name="price" id="fPrice" class="form-control" min="0" step="0.01" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Food photo <span class="text-muted fw-normal">(JPG / PNG / WEBP, max 3 MB)</span></label>
          <input type="file" name="image" id="fImage" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
          <div class="form-text" id="fImageHint">Leave empty to keep an emoji placeholder.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-brand">Save</button>
      </div>
    </form>
  </div>
</div>
<script>
const selectedIds = () => [...document.querySelectorAll('.row-check:checked')].map((c) => c.value);

function updateBulkBar() {
  const count = selectedIds().length;
  document.getElementById('bulkBar').style.setProperty('display', count ? 'flex' : 'none', 'important');
  document.getElementById('bulkIdleText').style.display = count ? 'none' : '';
  document.getElementById('bulkCount').textContent = count;
  const all = document.querySelectorAll('.row-check');
  const master = document.getElementById('checkAll');
  master.checked = all.length > 0 && count === all.length;
  master.indeterminate = count > 0 && count < all.length;
}
function toggleAll(master) {
  document.querySelectorAll('.row-check').forEach((c) => { c.checked = master.checked; });
  updateBulkBar();
}
function clearSelection() {
  document.querySelectorAll('.row-check').forEach((c) => { c.checked = false; });
  updateBulkBar();
}
function submitBulk(fields, ids) {
  const f = document.createElement('form');
  f.method = 'post';
  f.innerHTML = Object.entries(fields)
      .map(([k, v]) => '<input type="hidden" name="' + k + '" value="' + v + '">').join('')
    + ids.map((id) => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
  document.body.appendChild(f);
  f.submit();
}
function bulkAvailability(available) {
  const ids = selectedIds();
  if (!ids.length) return;
  submitBulk({ action: 'bulk_availability', available: available }, ids);
}
function bulkDelete() {
  const ids = selectedIds();
  if (!ids.length) return;
  appConfirm('Delete ' + ids.length + ' selected item(s)?', () => {
    submitBulk({ action: 'bulk_delete' }, ids);
  });
}
let itemModal;
function openItem(it) {
  document.getElementById('itemModalTitle').textContent = it ? 'Edit Item' : 'Add Item';
  document.getElementById('fId').value = it ? it.id : '';
  document.getElementById('fName').value = it ? it.name : '';
  document.getElementById('fCat').value = it ? it.category_id : document.getElementById('fCat').options[0].value;
  document.getElementById('fPrice').value = it ? it.price : '';
  document.getElementById('fImage').value = '';
  document.getElementById('fImageHint').textContent = it && it.image
    ? 'This item already has a photo — choosing a new file replaces it.'
    : 'Leave empty to keep an emoji placeholder.';
  itemModal = itemModal || new bootstrap.Modal(document.getElementById('itemModal'));
  itemModal.show();
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
