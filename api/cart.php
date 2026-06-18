<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

function get_cart(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare(
        'SELECT c.id AS cart_item_id, c.flower_id, c.variety_id, c.quantity,
                f.name, f.emoji, f.image,
                v.variety_name, v.color_hex, v.price, v.stock,
                (c.quantity * v.price) AS subtotal
         FROM cart c
         INNER JOIN flowers f ON f.id = c.flower_id
         INNER JOIN flower_varieties v ON v.id = c.variety_id
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
        $row['flower_id']    = (int)$row['flower_id'];
        $row['variety_id']   = (int)$row['variety_id'];
        $row['quantity']     = (int)$row['quantity'];
        $row['price']        = (float)$row['price'];
        $row['stock']        = (int)$row['stock'];
        $row['subtotal']     = (float)$row['subtotal'];

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

// Looks up a variety and confirms it actually belongs to the given flower —
// never trust price/stock sent from the browser.
function find_variety(mysqli $conn, int $flowerId, int $varietyId): ?array {
    $stmt = $conn->prepare(
        'SELECT v.id, v.variety_name, v.stock, v.price, f.name AS flower_name
         FROM flower_varieties v
         INNER JOIN flowers f ON f.id = v.flower_id
         WHERE v.id = ? AND v.flower_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $varietyId, $flowerId);
    $stmt->execute();

    $variety = $stmt->get_result()->fetch_assoc();
    return $variety ?: null;
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
    $flowerId  = (int)($data['flower_id'] ?? 0);
    $varietyId = (int)($data['variety_id'] ?? 0);
    $quantity  = max(1, (int)($data['quantity'] ?? 1));

    if ($flowerId <= 0 || $varietyId <= 0) {
        json_response(['success' => false, 'message' => 'Please choose a flower and a variety.'], 422);
    }

    $variety = find_variety($conn, $flowerId, $varietyId);

    if (!$variety) {
        json_response(['success' => false, 'message' => 'Variety not found.'], 404);
    }

    $existingQty = 0;

    $existing = $conn->prepare('SELECT quantity FROM cart WHERE user_id = ? AND flower_id = ? AND variety_id = ? LIMIT 1');
    $existing->bind_param('iii', $userId, $flowerId, $varietyId);
    $existing->execute();

    $current = $existing->get_result()->fetch_assoc();

    if ($current) {
        $existingQty = (int)$current['quantity'];
    }

    if ($existingQty + $quantity > (int)$variety['stock']) {
        json_response([
            'success' => false,
            'message' => $variety['flower_name'] . ' (' . $variety['variety_name'] . ') has only ' . $variety['stock'] . ' in stock.'
        ], 409);
    }

    if ($current) {
        $newQty = $existingQty + $quantity;

        $up = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND flower_id = ? AND variety_id = ?');
        $up->bind_param('iiii', $newQty, $userId, $flowerId, $varietyId);
        $up->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO cart (user_id, flower_id, variety_id, quantity) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiii', $userId, $flowerId, $varietyId, $quantity);
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
    $flowerId  = (int)($data['flower_id'] ?? 0);
    $varietyId = (int)($data['variety_id'] ?? 0);
    $quantity  = (int)($data['quantity'] ?? 0);

    if ($flowerId <= 0 || $varietyId <= 0) {
        json_response(['success' => false, 'message' => 'Please choose a flower and a variety.'], 422);
    }

    if ($quantity <= 0) {
        $del = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND flower_id = ? AND variety_id = ?');
        $del->bind_param('iii', $userId, $flowerId, $varietyId);
        $del->execute();
    } else {
        $variety = find_variety($conn, $flowerId, $varietyId);

        if (!$variety) {
            json_response(['success' => false, 'message' => 'Variety not found.'], 404);
        }

        if ($quantity > (int)$variety['stock']) {
            json_response([
                'success' => false,
                'message' => $variety['flower_name'] . ' (' . $variety['variety_name'] . ') has only ' . $variety['stock'] . ' in stock.'
            ], 409);
        }

        $up = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND flower_id = ? AND variety_id = ?');
        $up->bind_param('iiii', $quantity, $userId, $flowerId, $varietyId);
        $up->execute();
    }

    $cart = get_cart($conn, $userId);
    json_response(['success' => true] + $cart);
}

if ($action === 'remove') {
    $flowerId  = (int)($data['flower_id'] ?? 0);
    $varietyId = (int)($data['variety_id'] ?? 0);

    $del = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND flower_id = ? AND variety_id = ?');
    $del->bind_param('iii', $userId, $flowerId, $varietyId);
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
