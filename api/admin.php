<?php
require_once 'db.php';
require_admin();

$action = $_GET['action'] ?? '';
$data = request_data();

function admin_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function ensure_user_status_column(mysqli $conn): void {
    if (!admin_column_exists($conn, 'users', 'is_active')) {
        $conn->query('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role');
    }
}

ensure_user_status_column($conn);

if ($action === 'stats') {
    $orders = (int)$conn->query('SELECT COUNT(*) AS c FROM orders')->fetch_assoc()['c'];
    $products = (int)$conn->query('SELECT COUNT(*) AS c FROM flowers')->fetch_assoc()['c'];
    $customers = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()['c'];
    $revenue = (float)$conn->query('SELECT COALESCE(SUM(total), 0) AS t FROM orders')->fetch_assoc()['t'];
    $messages = 0;

    $messageTable = $conn->query(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'contact_messages'"
    )->fetch_assoc();

    if ((int)$messageTable['c'] > 0) {
        $messages = (int)$conn->query('SELECT COUNT(*) AS c FROM contact_messages')->fetch_assoc()['c'];
    }

    json_response([
        'success' => true,
        'stats' => [
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers,
            'revenue' => round($revenue, 2),
            'messages' => $messages
        ]
    ]);
}

if ($action === 'orders') {
    $result = $conn->query(
        'SELECT 
            o.id,
            o.total,
            o.status,
            o.notes,
            o.payment_method,
            o.payment_status,
            o.recipient_name AS cod_recipient_name,
            o.recipient_phone AS cod_phone,
            o.delivery_address AS cod_address,
            NULL AS cod_city,
            o.delivery_time AS cod_delivery_time,
            o.created_at,
            u.name,
            u.email
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ORDER BY o.id DESC'
    );

    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['total'] = (float)$row['total'];
        $orders[] = $row;
    }

    json_response([
        'success' => true,
        'orders' => $orders
    ]);
}

if ($action === 'update-order') {
    $id = (int)($data['id'] ?? 0);
    $status = strtolower(trim($data['status'] ?? ''));

    $allowed = ['pending', 'processing', 'delivered', 'cancelled'];

    if ($id <= 0 || !in_array($status, $allowed, true)) {
        json_response([
            'success' => false,
            'message' => 'Invalid order status.'
        ], 422);
    }

    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Order updated.'
    ]);
}

if ($action === 'products') {
    $result = $conn->query(
        'SELECT id, name, emoji, image, price, meaning, tag, stock 
         FROM flowers 
         ORDER BY id DESC'
    );

    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];

        if (empty($row['image'])) {
            $row['image'] = 'images/flowers/default.jpg';
        }

        $products[] = $row;
    }

    json_response([
        'success' => true,
        'products' => $products
    ]);
}

if ($action === 'save-product') {
    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $emoji = trim($data['emoji'] ?? '🌸');
    $image = trim($data['image'] ?? 'images/flowers/default.jpg');
    $price = (float)($data['price'] ?? 0);
    $tag = trim($data['tag'] ?? '');
    $meaning = trim($data['meaning'] ?? '');
    $stock = max(0, (int)($data['stock'] ?? 0));

    if ($name === '' || $price <= 0 || $meaning === '') {
        json_response([
            'success' => false,
            'message' => 'Name, price and meaning are required.'
        ], 422);
    }

    if ($image === '') {
        $image = 'images/flowers/default.jpg';
    }

    if ($id > 0) {
        $stmt = $conn->prepare(
            'UPDATE flowers 
             SET name = ?, emoji = ?, image = ?, price = ?, tag = ?, meaning = ?, stock = ? 
             WHERE id = ?'
        );

        $stmt->bind_param(
            'sssdssii',
            $name,
            $emoji,
            $image,
            $price,
            $tag,
            $meaning,
            $stock,
            $id
        );

        $stmt->execute();

        json_response([
            'success' => true,
            'message' => 'Product updated.'
        ]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO flowers (name, emoji, image, price, tag, meaning, stock) 
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->bind_param(
        'sssdssi',
        $name,
        $emoji,
        $image,
        $price,
        $tag,
        $meaning,
        $stock
    );

    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Product added.'
    ]);
}

if ($action === 'delete-product') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

    if ($id <= 0) {
        json_response([
            'success' => false,
            'message' => 'Invalid product id.'
        ], 422);
    }

    $stmt = $conn->prepare('DELETE FROM flowers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Product deleted.'
    ]);
}

if ($action === 'users') {
    $result = $conn->query(
        'SELECT id, name, email, role, is_active, created_at 
         FROM users 
         ORDER BY id DESC'
    );

    $users = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_active'] = (int)$row['is_active'];
        $users[] = $row;
    }

    json_response([
        'success' => true,
        'users' => $users
    ]);
}

if ($action === 'update-user-status') {
    $id = (int)($data['id'] ?? 0);
    $isActive = (int)($data['is_active'] ?? 0) === 1 ? 1 : 0;

    if ($id <= 0) {
        json_response([
            'success' => false,
            'message' => 'Invalid customer id.'
        ], 422);
    }

    $stmt = $conn->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        json_response([
            'success' => false,
            'message' => 'Customer not found.'
        ], 404);
    }

    if (strtolower($user['role']) !== 'customer') {
        json_response([
            'success' => false,
            'message' => 'Only customer accounts can be activated or deactivated.'
        ], 422);
    }

    $update = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $update->bind_param('ii', $isActive, $id);
    $update->execute();

    json_response([
        'success' => true,
        'message' => $isActive ? 'Customer activated.' : 'Customer deactivated.'
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown admin action.'
], 404);
?>
