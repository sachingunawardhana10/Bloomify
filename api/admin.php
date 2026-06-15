<?php
require_once 'db.php';
require_admin();

$action = $_GET['action'] ?? '';
$data = request_data();

if ($action === 'stats') {
    $orders = (int)$conn->query('SELECT COUNT(*) AS c FROM orders')->fetch_assoc()['c'];
    $products = (int)$conn->query('SELECT COUNT(*) AS c FROM flowers')->fetch_assoc()['c'];
    $customers = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()['c'];
    $revenue = (float)$conn->query('SELECT COALESCE(SUM(total), 0) AS t FROM orders')->fetch_assoc()['t'];

    json_response([
        'success' => true,
        'stats' => [
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers,
            'revenue' => round($revenue, 2)
        ]
    ]);
}

if ($action === 'orders') {
    $result = $conn->query(
        'SELECT o.id, o.total, o.status, o.notes, o.created_at, u.name, u.email
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

    json_response(['success' => true, 'orders' => $orders]);
}

if ($action === 'update-order') {
    $id = (int)($data['id'] ?? 0);
    $status = strtolower(trim($data['status'] ?? ''));

    $allowed = ['pending', 'processing', 'delivered', 'cancelled'];

    if ($id <= 0 || !in_array($status, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Invalid order status.'], 422);
    }

    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    json_response(['success' => true, 'message' => 'Order updated.']);
}

if ($action === 'products') {
    $result = $conn->query('SELECT id, name, emoji, price, meaning, tag, stock FROM flowers ORDER BY id DESC');

    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        $products[] = $row;
    }

    json_response(['success' => true, 'products' => $products]);
}

if ($action === 'save-product') {
    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $emoji = trim($data['emoji'] ?? '🌸');
    $price = (float)($data['price'] ?? 0);
    $tag = trim($data['tag'] ?? '');
    $meaning = trim($data['meaning'] ?? '');
    $stock = max(0, (int)($data['stock'] ?? 0));

    if ($name === '' || $price <= 0 || $meaning === '') {
        json_response(['success' => false, 'message' => 'Name, price and meaning are required.'], 422);
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE flowers SET name = ?, emoji = ?, price = ?, tag = ?, meaning = ?, stock = ? WHERE id = ?');
        $stmt->bind_param('ssdssii', $name, $emoji, $price, $tag, $meaning, $stock, $id);
        $stmt->execute();

        json_response(['success' => true, 'message' => 'Product updated.']);
    }

    $stmt = $conn->prepare('INSERT INTO flowers (name, emoji, price, tag, meaning, stock) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssdssi', $name, $emoji, $price, $tag, $meaning, $stock);
    $stmt->execute();

    json_response(['success' => true, 'message' => 'Product added.']);
}

if ($action === 'delete-product') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

    if ($id <= 0) {
        json_response(['success' => false, 'message' => 'Invalid product id.'], 422);
    }

    $stmt = $conn->prepare('DELETE FROM flowers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_response(['success' => true, 'message' => 'Product deleted.']);
}

if ($action === 'users') {
    $result = $conn->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC');

    $users = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $users[] = $row;
    }

    json_response(['success' => true, 'users' => $users]);
}

json_response(['success' => false, 'message' => 'Unknown admin action.'], 404);
?>