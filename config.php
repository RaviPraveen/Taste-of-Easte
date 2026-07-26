<?php
session_start();
date_default_timezone_set('Asia/Colombo');

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'food_hotel_pos');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    install($pdo);
    return $pdo;
}

function install(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        available TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(30) NOT NULL UNIQUE,
        order_type ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'takeaway',
        table_no VARCHAR(20) DEFAULT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        service_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_method ENUM('cash','card') NOT NULL DEFAULT 'cash',
        paid DECIMAL(10,2) NOT NULL DEFAULT 0,
        change_due DECIMAL(10,2) NOT NULL DEFAULT 0,
        status ENUM('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        qty INT NOT NULL DEFAULT 1,
        line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(50) PRIMARY KEY,
        value TEXT
    ) ENGINE=InnoDB");

    // Migrations for installs created before these features existed.
    if (!$pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image'")->fetch()) {
        $pdo->exec('ALTER TABLE menu_items ADD COLUMN image VARCHAR(255) DEFAULT NULL');
    }
    // Kitchen module removed — fold its intermediate statuses into 'completed'.
    // 'pending' stays: it now means a paid order not yet marked done on the Orders page.
    $pdo->exec("UPDATE orders SET status = 'completed' WHERE status IN ('preparing','ready')");

    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        seed($pdo);
    }
}

function seed(PDO $pdo): void
{
    $u = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)');
    $u->execute(['Administrator', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    $u->execute(['Cashier', 'cashier', password_hash('cashier123', PASSWORD_DEFAULT), 'cashier']);

    $defaults = [
        'hotel_name'         => 'New Ceylon Food Hotel',
        'address'            => 'No. 45, Main Street, Colombo',
        'phone'              => '011-2345678',
        'currency'           => 'Rs.',
        'service_charge_pct' => '10',
        'receipt_footer'     => 'Thank you! Come again.',
    ];
    $s = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?,?)');
    foreach ($defaults as $k => $v) {
        $s->execute([$k, $v]);
    }

    $menu = [
        'Rice & Curry' => [
            ['Chicken Rice & Curry', 450],
            ['Fish Rice & Curry', 480],
            ['Egg Rice & Curry', 350],
            ['Vegetable Rice & Curry', 300],
        ],
        'Kottu' => [
            ['Chicken Kottu', 750],
            ['Cheese Chicken Kottu', 950],
            ['Egg Kottu', 650],
            ['Vegetable Kottu', 600],
        ],
        'Fried Rice' => [
            ['Chicken Fried Rice', 700],
            ['Seafood Fried Rice', 850],
            ['Egg Fried Rice', 600],
            ['Vegetable Fried Rice', 550],
        ],
        'Hoppers & String Hoppers' => [
            ['Plain Hopper', 50],
            ['Egg Hopper', 100],
            ['Milk Hopper', 80],
            ['String Hoppers (10 pcs)', 200],
        ],
        'Short Eats' => [
            ['Fish Bun', 100],
            ['Egg Roll', 120],
            ['Ulundu Vadai', 80],
            ['Vegetable Samosa', 100],
        ],
        'Beverages' => [
            ['Milk Tea', 120],
            ['Plain Tea', 60],
            ['Coffee', 150],
            ['Faluda', 350],
            ['Fresh Lime Juice', 200],
            ['Bottled Water 500ml', 100],
        ],
    ];
    $c  = $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (?,?)');
    $mi = $pdo->prepare('INSERT INTO menu_items (category_id, name, price) VALUES (?,?,?)');
    $sort = 1;
    foreach ($menu as $cat => $items) {
        $c->execute([$cat, $sort++]);
        $catId = (int) $pdo->lastInsertId();
        foreach ($items as [$name, $price]) {
            $mi->execute([$catId, $name, $price]);
        }
    }
}

function settings(): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (db()->query('SELECT name, value FROM settings') as $row) {
            $all[$row['name']] = $row['value'];
        }
    }
    return $all;
}

function setting(string $key, string $default = ''): string
{
    return settings()[$key] ?? $default;
}

function rs($amount): string
{
    return setting('currency', 'Rs.') . ' ' . number_format((float) $amount, 2);
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: pos.php');
        exit;
    }
}

function flash(string $msg = null, string $type = 'success'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function food_emoji(string $name, string $category = ''): string
{
    $s = strtolower($name . ' ' . $category);
    $map = [
        'kottu' => '🥘', 'fried rice' => '🍚', 'biryani' => '🍛', 'rice' => '🍛',
        'string hopper' => '🍜', 'hopper' => '🥞', 'noodle' => '🍜',
        'bun' => '🥯', 'roll' => '🌯', 'vadai' => '🍩', 'samosa' => '🥟',
        'roti' => '🫓', 'kebab' => '🥙', 'burger' => '🍔', 'pizza' => '🍕',
        'tea' => '🍵', 'coffee' => '☕', 'faluda' => '🥤', 'juice' => '🧃',
        'lime' => '🍋', 'water' => '💧', 'milk' => '🥛', 'ice cream' => '🍨',
        'egg' => '🍳', 'fish' => '🐟', 'chicken' => '🍗', 'cheese' => '🧀',
        'seafood' => '🦐', 'vegetable' => '🥗', 'dessert' => '🍮',
    ];
    foreach ($map as $key => $emoji) {
        if (str_contains($s, $key)) {
            return $emoji;
        }
    }
    return '🍽️';
}

function next_order_no(PDO $pdo): string
{
    $today = date('Y-m-d');
    $count = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn();
    return 'FH' . date('ymd') . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
}
