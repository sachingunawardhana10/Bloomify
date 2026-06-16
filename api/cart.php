<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

function get_cart(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare(
        'SELECT c.id AS cart_item_id, c.flower_id, c.quantity, f.name, f.emoji, f.price, f.stock,
                (c.quantity * f.price) AS subtotal
         FROM cart c
         INNER JOIN flowers f ON f.id = c.flower_id
         WHERE c.user_id = ?
         ORDER BY c.id ASC'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = [];
    $total = 0.0;
    $count = 0;

    foreach ($rows as $row) {
        $row['cart_item_id'] = (int)$row['cart_item_id'];
        $row['flower_id'] = (int)$row['flower_id'];
        $row['quantity'] = (int)$row['quantity'];
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        $row['subtotal'] = (float)$row['subtotal'];

        $total += $row['subtotal'];
        $count += $row['quantity'];

        $items[] = $row;
    }

    return [
        'items' => $items,
        'total' => round($total, 2),
        'count' => $count
    ];
}

if ($action === 'get') {
    $cart = get_cart($conn, $userId);
    json_response(['success' => true] + $cart);
}

if ($action === 'count') {
    $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS count_items FROM cart WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $count = (int)$stmt->get_result()->fetch_assoc()['count_items'];
    json_response(['success' => true, 'count' => $count]);
}

if ($action === 'add') {
    $flowerId = (int)($data['flower_id'] ?? 0);
    $quantity = max(1, (int)($data['quantity'] ?? 1));

    if ($flowerId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid flower selected.'], 422);
    }

    $stmt = $conn->prepare('SELECT id, name, stock FROM flowers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $flowerId);
    $stmt->execute();

    $flower = $stmt->get_result()->fetch_assoc();

    if (!$flower) {
        json_response(['success' => false, 'message' => 'Flower not found.'], 404);
    }

    $existingQty = 0;

    $existing = $conn->prepare('SELECT quantity FROM cart WHERE user_id = ? AND flower_id = ? LIMIT 1');
    $existing->bind_param('ii', $userId, $flowerId);
    $existing->execute();

    $current = $existing->get_result()->fetch_assoc();

    if ($current) {
        $existingQty = (int)$current['quantity'];
    }

    if ($existingQty + $quantity > (int)$flower['stock']) {
        json_response([
            'success' => false,
            'message' => $flower['name'] . ' has only ' . $flower['stock'] . ' in stock.'
        ], 409);
    }

    if ($current) {
        $newQty = $existingQty + $quantity;

        $up = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND flower_id = ?');
        $up->bind_param('iii', $newQty, $userId, $flowerId);
        $up->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO cart (user_id, flower_id, quantity) VALUES (?, ?, ?)');
        $ins->bind_param('iii', $userId, $flowerId, $quantity);
        $ins->execute();
    }

    $cart = get_cart($conn, $userId);

    json_response([
        'success' => true,
        'message' => 'Added to cart.',
        'count' => $cart['count']
    ]);
}

if ($action === 'update') {
    $flowerId = (int)($data['flower_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);

    if ($flowerId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid flower selected.'], 422);
    }

    if ($quantity <= 0) {
        $del = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND flower_id = ?');
        $del->bind_param('ii', $userId, $flowerId);
        $del->execute();
    } else {
        $stock = $conn->prepare('SELECT stock, name FROM flowers WHERE id = ? LIMIT 1');
        $stock->bind_param('i', $flowerId);
        $stock->execute();

        $flower = $stock->get_result()->fetch_assoc();

        if (!$flower) {
            json_response(['success' => false, 'message' => 'Flower not found.'], 404);
        }

        if ($quantity > (int)$flower['stock']) {
            json_response([
                'success' => false,
                'message' => $flower['name'] . ' has only ' . $flower['stock'] . ' in stock.'
            ], 409);
        }

        $up = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND flower_id = ?');
        $up->bind_param('iii', $quantity, $userId, $flowerId);
        $up->execute();
    }

    $cart = get_cart($conn, $userId);
    json_response(['success' => true] + $cart);
}

if ($action === 'remove') {
    $flowerId = (int)($data['flower_id'] ?? 0);

    $del = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND flower_id = ?');
    $del->bind_param('ii', $userId, $flowerId);
    $del->execute();

    $cart = get_cart($conn, $userId);
    json_response(['success' => true] + $cart);
}

if ($action === 'clear') {
    $del = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
    $del->bind_param('i', $userId);
    $del->execute();

    json_response([
        'success' => true,
        'items' => [],
        'total' => 0,
        'count' => 0
    ]);
}

json_response(['success' => false, 'message' => 'Unknown cart action.'], 404);
?>