<?php
require_once 'db.php';

$userId = require_login();

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    json_response([
        'success' => false,
        'message' => 'Invalid order ID.'
    ], 422);
}

try {
    $stmt = $conn->prepare(
        "SELECT 
            id,
            user_id,
            total,
            status,
            payment_method,
            payment_status,
            payment_reference,
            created_at
         FROM orders
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        json_response([
            'success' => false,
            'message' => 'Order not found for this user.'
        ], 404);
    }

    $order['id'] = (int)$order['id'];
    $order['user_id'] = (int)$order['user_id'];
    $order['total'] = (float)$order['total'];

    json_response([
        'success' => true,
        'order' => $order
    ]);

} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Payment status check failed.',
        'error' => $e->getMessage()
    ], 500);
}
?>