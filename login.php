<?php
require_once __DIR__ . '/config.php';
if (current_user()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role']];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | <?= e(setting('hotel_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css?v=4" rel="stylesheet">
</head>
<body class="login-page d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card login-card shadow-lg" style="width: 400px;">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <?php if (setting('logo_img')): ?>
          <img src="<?= e(setting('logo_img')) ?>" alt="logo" class="login-logo-img mb-3">
        <?php else: ?>
          <div class="login-logo mb-3"><i class="bi bi-shop" style="color:var(--primary)"></i></div>
        <?php endif; ?>
        <h1 class="h4 mb-1"><?= e(setting('hotel_name')) ?></h1>
        <div class="text-muted" style="font-size:.85rem">Sign in to your POS account</div>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle"></i><?= e($error) ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label small fw-medium" for="loginUser">Username</label>
          <input type="text" name="username" id="loginUser" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="mb-4">
          <label class="form-label small fw-medium" for="loginPass">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="loginPass" class="form-control" required autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()" aria-label="Show password" style="border-radius:0 10px 10px 0">
              <i class="bi bi-eye" id="passEye"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-brand w-100 py-2 fw-medium">Sign In</button>
      </form>
    </div>
  </div>
<script>
function togglePass() {
  const input = document.getElementById('loginPass');
  const eye = document.getElementById('passEye');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  eye.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>
</body>
</html>
