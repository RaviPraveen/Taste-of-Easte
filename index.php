<?php
require_once __DIR__ . '/config.php';
db();
if (!current_user()) {
    header('Location: login.php');
    exit;
}
header('Location: ' . (is_admin() ? 'dashboard.php' : 'pos.php'));
exit;
