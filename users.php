<?php
require_once __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'cashier';
        $password = $_POST['password'] ?? '';
        if ($name === '' || $username === '') {
            flash('Name and username are required.', 'danger');
        } else {
            $dup = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $dup->execute([$username, $id]);
            if ($dup->fetch()) {
                flash('That username is already taken.', 'danger');
            } elseif ($id) {
                if ($password !== '') {
                    db()->prepare('UPDATE users SET name=?, username=?, role=?, password=? WHERE id=?')
                        ->execute([$name, $username, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare('UPDATE users SET name=?, username=?, role=? WHERE id=?')
                        ->execute([$name, $username, $role, $id]);
                }
                flash('User updated.');
            } elseif ($password === '') {
                flash('Password is required for a new user.', 'danger');
            } else {
                db()->prepare('INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)')
                    ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('User added.');
            }
        }
    } elseif ($action === 'toggle' && $id && $id !== (int) current_user()['id']) {
        db()->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$id]);
    }
    header('Location: users.php');
    exit;
}

$page_title = 'Users';
$active = 'users';
require_once __DIR__ . '/header.php';
$users = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small">Manage staff logins and roles</div>
  <button class="btn btn-sm btn-brand" onclick="openUser()"><i class="bi bi-plus-lg"></i> Add User</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Username</th><th>Role</th><th class="text-center">Active</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="<?= $u['active'] ? '' : 'table-secondary opacity-75' ?>">
          <td class="fw-semibold"><?= e($u['name']) ?><?= (int) $u['id'] === (int) current_user()['id'] ? ' <span class="badge text-bg-light border">you</span>' : '' ?></td>
          <td><?= e($u['username']) ?></td>
          <td><span class="badge text-bg-<?= $u['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
          <td class="text-center">
            <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <div class="form-check form-switch d-inline-block m-0">
                <input class="form-check-input" type="checkbox" role="switch" <?= $u['active'] ? 'checked' : '' ?>
                  onchange="this.form.submit()" aria-label="Toggle active state of <?= e($u['name']) ?>">
              </div>
            </form>
            <?php else: ?>
              <span class="badge text-bg-success">Active</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary"
              onclick='openUser(<?= json_encode(['id' => (int) $u['id'], 'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role']]) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="uId">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Full name</label>
          <input type="text" name="name" id="uName" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Username</label>
          <input type="text" name="username" id="uUsername" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Role</label>
          <select name="role" id="uRole" class="form-select">
            <option value="cashier">Cashier</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Password <span class="text-muted fw-normal" id="uPassHint">(leave blank to keep current)</span></label>
          <input type="password" name="password" id="uPassword" class="form-control">
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
let userModal;
function openUser(u) {
  document.getElementById('userModalTitle').textContent = u ? 'Edit User' : 'Add User';
  document.getElementById('uId').value = u ? u.id : '';
  document.getElementById('uName').value = u ? u.name : '';
  document.getElementById('uUsername').value = u ? u.username : '';
  document.getElementById('uRole').value = u ? u.role : 'cashier';
  document.getElementById('uPassword').value = '';
  document.getElementById('uPassHint').style.display = u ? '' : 'none';
  userModal = userModal || new bootstrap.Modal(document.getElementById('userModal'));
  userModal.show();
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
