<?php
require_once __DIR__ . '/config.php';
require_login();
$active = $active ?? '';
$user = current_user();
$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'POS') ?> | <?= e(setting('hotel_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css?v=5" rel="stylesheet">
</head>
<body data-session-user="<?= (int) $user['id'] ?>:<?= e($user['role']) ?>">
<script>
// Apply the saved sidebar state before paint to avoid a layout flash.
if (localStorage.getItem('sbCollapsed') === '1') document.body.classList.add('sb-collapsed');
</script>
<div class="layout">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <?php if (setting('logo_img')): ?>
      <img src="<?= e(setting('logo_img')) ?>" alt="logo" class="brand-logo-img">
    <?php else: ?>
      <span class="brand-icon"><i class="bi bi-shop"></i></span>
    <?php endif; ?>
    <div class="brand-text overflow-hidden">
      <div class="brand-name text-truncate"><?= e(setting('hotel_name')) ?></div>
      <div class="brand-sub">Point of Sale</div>
    </div>
  </div>

  <nav class="sidebar-nav" aria-label="Main navigation">
    <div class="nav-section">Main</div>
    <?php if (is_admin()): ?>
    <a class="side-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i><span class="lbl">Dashboard</span></a>
    <?php endif; ?>
    <a class="side-link <?= $active === 'pos' ? 'active' : '' ?>" href="pos.php"><i class="bi bi-grid-1x2"></i><span class="lbl">POS Billing</span></a>
    <?php $pendingCount = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(); ?>
    <a class="side-link <?= $active === 'orders' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-receipt"></i><span class="lbl">Orders</span><?php if ($pendingCount > 0): ?><span class="nav-badge pulse"><?= $pendingCount ?></span><?php endif; ?></a>
    <?php if (is_admin()): ?>
    <div class="nav-section">Catalog</div>
    <a class="side-link <?= $active === 'categories' ? 'active' : '' ?>" href="categories.php"><i class="bi bi-tags"></i><span class="lbl">Create Categories</span></a>
    <a class="side-link <?= $active === 'menu' ? 'active' : '' ?>" href="menu.php"><i class="bi bi-journal-plus"></i><span class="lbl">Create Items</span></a>
    <div class="nav-section">Management</div>
    <a class="side-link <?= $active === 'designer' ? 'active' : '' ?>" href="receipt_designer.php"><i class="bi bi-palette"></i><span class="lbl">Bill Designer</span></a>
    <a class="side-link <?= $active === 'reports' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-bar-chart"></i><span class="lbl">Reports</span></a>
    <div class="nav-section">System</div>
    <a class="side-link <?= $active === 'users' ? 'active' : '' ?>" href="users.php"><i class="bi bi-people"></i><span class="lbl">Users</span></a>
    <a class="side-link <?= $active === 'settings' ? 'active' : '' ?>" href="settings.php"><i class="bi bi-gear"></i><span class="lbl">Settings</span></a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="avatar"><?= e($initial) ?></div>
    <div class="user-meta flex-grow-1 overflow-hidden">
      <div class="user-name text-truncate"><?= e($user['name']) ?></div>
      <div class="user-role"><?= e(ucfirst($user['role'])) ?></div>
    </div>
    <a href="logout.php" class="logout-btn" title="Logout" aria-label="Logout"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="content">
  <header class="topbar">
    <button class="icon-btn d-lg-none" onclick="toggleSidebar()" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <button class="icon-btn d-none d-lg-inline-flex" onclick="toggleCollapse()" aria-label="Collapse sidebar" title="Collapse sidebar"><i class="bi bi-layout-sidebar"></i></button>
    <div>
      <div class="crumb"><a href="index.php"><i class="bi bi-house-door"></i> Home</a> <span class="mx-1">/</span> <?= e($page_title ?? '') ?></div>
      <h1 class="topbar-title"><?= e($page_title ?? '') ?></h1>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="date-chip d-none d-md-inline"><i class="bi bi-calendar3 me-1"></i><?= e(date('D, d M Y')) ?></span>
      <span class="date-chip"><i class="bi bi-clock me-1"></i><span id="liveClock"><?= e(date('h:i:s A')) ?></span></span>
      <a href="pos.php" class="btn btn-brand btn-sm d-none d-sm-inline-flex align-items-center gap-1"><i class="bi bi-plus-lg"></i> New Sale</a>
      <div class="dropdown">
        <button class="profile-btn" data-bs-toggle="dropdown" aria-label="Profile menu">
          <span class="avatar"><?= e($initial) ?></span>
          <i class="bi bi-chevron-down text-muted" style="font-size:.7rem"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="px-3 py-2">
            <div class="fw-semibold" style="font-size:.85rem"><?= e($user['name']) ?></div>
            <div class="text-muted" style="font-size:.72rem"><?= e(ucfirst($user['role'])) ?></div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <?php if (is_admin()): ?>
          <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
          <?php endif; ?>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="page-body">
<?php if ($f = flash()): ?>
  <div class="toast-container position-fixed top-0 end-0 p-3">
    <div class="toast app-toast t-<?= $f['type'] === 'success' ? 'success' : ($f['type'] === 'danger' ? 'danger' : 'warning') ?> d-flex align-items-center" role="alert" aria-live="polite">
      <span class="toast-accent"></span>
      <i class="toast-icon bi <?= $f['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> ms-3"></i>
      <div class="toast-body flex-grow-1"><?= e($f['msg']) ?></div>
      <button type="button" class="btn-close me-3" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
<?php endif; ?>
