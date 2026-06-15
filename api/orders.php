<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

if ($action === 'place') {
    $notes = trim($data['notes'] ?? '');

    $stmt = $conn->prepare(
        'SELECT c.flower_id, c.quantity, f.name, f.price, f.stock
         FROM cart c
         INNER JOIN flowers f ON f.id = c.flower_id
         WHERE c.user_id = ?'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($cart)) {
        json_response(['success' => false, 'message' => 'Your cart is empty.'], 422);
    }

    $total = 0.0;

    foreach ($cart as $item) {
        if ((int)$item['quantity'] > (int)$item['stock']) {
            json_response([
                'success' => false,
                'message' => $item['name'] . ' has only ' . $item['stock'] . ' in stock.'
            ], 409);
        }

        $total += (float)$item['price'] * (int)$item['quantity'];
    }

    $conn->begin_transaction();

    try {
        $status = 'pending';

        $order = $conn->prepare('INSERT INTO orders (user_id, total, status, notes) VALUES (?, ?, ?, ?)');
        $order->bind_param('idss', $userId, $total, $status, $notes);
        $order->execute();

        $orderId = $conn->insert_id;

        foreach ($cart as $item) {
            $orderItem = $conn->prepare('INSERT INTO order_items (order_id, flower_id, quantity, price) VALUES (?, ?, ?, ?)');
            $orderItem->bind_param('iiid', $orderId, $item['flower_id'], $item['quantity'], $item['price']);
            $orderItem->execute();

            $stock = $conn->prepare('UPDATE flowers SET stock = stock - ? WHERE id = ?');
            $stock->bind_param('ii', $item['quantity'], $item['flower_id']);
            $stock->execute();
        }

        $clear = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
        $clear->bind_param('i', $userId);
        $clear->execute();

        $conn->commit();

        json_response([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order_id' => (int)$orderId,
            'total' => round($total, 2)
        ]);
    } catch (Throwable $e) {
        $conn->rollback();

        json_response([
            'success' => false,
            'message' => 'Order failed: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'mine') {
    $stmt = $conn->prepare('SELECT id, total, status, notes, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['total'] = (float)$order['total'];

        $items = $conn->prepare(
            'SELECT oi.flower_id, oi.quantity, oi.price, f.name, f.emoji
             FROM order_items oi
             INNER JOIN flowers f ON f.id = oi.flower_id
             WHERE oi.order_id = ?'
        );

        $items->bind_param('i', $order['id']);
        $items->execute();

        $order['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    json_response(['success' => true, 'orders' => $orders]);
}

json_response(['success' => false, 'message' => 'Unknown orders action.'], 404);
?>