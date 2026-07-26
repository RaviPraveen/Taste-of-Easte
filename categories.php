<?php
require_once __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'add' && $name !== '') {
        $max = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();
        db()->prepare('INSERT INTO categories (name, sort_order) VALUES (?,?)')->execute([$name, $max + 1]);
        flash('Category added.');
    } elseif ($action === 'rename' && $id && $name !== '') {
        db()->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
        flash('Category renamed.');
    } elseif ($action === 'delete' && $id) {
        $count = db()->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            flash('Cannot delete — this category still has menu items.', 'danger');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('Category deleted.');
        }
    }
    header('Location: categories.php');
    exit;
}

$page_title = 'Categories';
$active = 'categories';
require_once __DIR__ . '/header.php';

$categories = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM menu_items mi WHERE mi.category_id = c.id) AS item_count
     FROM categories c ORDER BY c.sort_order, c.name'
)->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small">Group your menu items for the POS screen</div>
  <a href="menu.php" class="btn btn-sm btn-outline-brand"><i class="bi bi-arrow-left"></i> Back to Menu</a>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Name</th><th class="text-center">Items</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td>
                <form method="post" class="d-flex gap-2">
                  <input type="hidden" name="action" value="rename">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input type="text" name="name" class="form-control form-control-sm" value="<?= e($c['name']) ?>" required>
                  <button class="btn btn-sm btn-outline-secondary" title="Rename"><i class="bi bi-check-lg"></i></button>
                </form>
              </td>
              <td class="text-center"><span class="badge text-bg-light border"><?= (int) $c['item_count'] ?></span></td>
              <td class="text-end">
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this category?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" <?= $c['item_count'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">Add Category</div>
      <div class="card-body">
        <form method="post" class="d-flex gap-2">
          <input type="hidden" name="action" value="add">
          <input type="text" name="name" class="form-control" placeholder="Category name" required>
          <button class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
