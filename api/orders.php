<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

if ($action === 'place') {
    $notes = trim($data['notes'] ?? '');
    $paymentMethod = trim($data['payment_method'] ?? 'Cash on Delivery');
    $codDetails = is_array($data['cod_details'] ?? null) ? $data['cod_details'] : [];
    $codRecipientName = trim($codDetails['recipient_name'] ?? '');
    $codPhone = trim($codDetails['phone'] ?? '');
    $codAddress = trim($codDetails['address'] ?? '');
    $codCity = trim($codDetails['city'] ?? '');
    $codDeliveryTime = trim($codDetails['delivery_time'] ?? '');
    $deliveryAddress = $codAddress;

    $allowedPaymentMethods = [
        'Cash on Delivery',
        'PayHere Online'
    ];

    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        json_response([
            'success' => false,
            'message' => 'Invalid payment method.'
        ], 422);
    }

    $paymentStatus = $paymentMethod === 'PayHere Online' ? 'Pending' : 'Unpaid';

    if ($paymentMethod === 'Cash on Delivery') {
        if ($codRecipientName === '' || $codPhone === '' || $codAddress === '' || $codCity === '') {
            json_response([
                'success' => false,
                'message' => 'Recipient name, phone number, delivery address and city are required for Cash on Delivery.'
            ], 422);
        }

        $deliveryAddress = $codAddress . "\nCity / area: " . $codCity;
    } else {
        $codRecipientName = '';
        $codPhone = '';
        $deliveryAddress = '';
        $codCity = '';
        $codDeliveryTime = '';
    }

    $stmt = $conn->prepare(
        'SELECT 
            c.flower_id,
            c.variety_id,
            c.quantity,
            f.name,
            v.variety_name,
            v.price,
            v.stock
         FROM cart c
         INNER JOIN flowers f ON f.id = c.flower_id
         INNER JOIN flower_varieties v ON v.id = c.variety_id
         WHERE c.user_id = ?'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($cart)) {
        json_response([
            'success' => false,
            'message' => 'Your cart is empty.'
        ], 422);
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

        $order = $conn->prepare(
            'INSERT INTO orders 
            (
                user_id,
                total,
                status,
                notes,
                payment_method,
                payment_status,
                recipient_name,
                recipient_phone,
                delivery_address,
                delivery_time
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $order->bind_param(
            'idssssssss',
            $userId,
            $total,
            $status,
            $notes,
            $paymentMethod,
            $paymentStatus,
            $codRecipientName,
            $codPhone,
            $deliveryAddress,
            $codDeliveryTime
        );

        $order->execute();
        $orderId = $conn->insert_id;

        foreach ($cart as $item) {
            $orderItem = $conn->prepare(
                'INSERT INTO order_items 
                (
                    order_id,
                    flower_id,
                    variety_id,
                    quantity,
                    price
                )
                VALUES (?, ?, ?, ?, ?)'
            );

            $orderItem->bind_param(
                'iiiid',
                $orderId,
                $item['flower_id'],
                $item['variety_id'],
                $item['quantity'],
                $item['price']
            );

            $orderItem->execute();

            // Reserve stock immediately after order creation.
            // For a student project, this is simple and practical.
            $stockVariety = $conn->prepare(
                'UPDATE flower_varieties
                 SET stock = stock - ?
                 WHERE id = ?'
            );

            $stockVariety->bind_param(
                'ii',
                $item['quantity'],
                $item['variety_id']
            );

            $stockVariety->execute();

            $stockFlower = $conn->prepare(
                'UPDATE flowers
                 SET stock = GREATEST(stock - ?, 0)
                 WHERE id = ?'
            );

            $stockFlower->bind_param(
                'ii',
                $item['quantity'],
                $item['flower_id']
            );

            $stockFlower->execute();
        }

        $clear = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
        $clear->bind_param('i', $userId);
        $clear->execute();

        $conn->commit();

        json_response([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order_id' => (int)$orderId,
            'total' => round($total, 2),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus
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
    $stmt = $conn->prepare(
        'SELECT 
            id,
            total,
            status,
            notes,
            payment_method,
            payment_status,
            payment_reference,
            recipient_name AS cod_recipient_name,
            recipient_phone AS cod_phone,
            delivery_address AS cod_address,
            NULL AS cod_city,
            delivery_time AS cod_delivery_time,
            created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY id DESC'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['total'] = (float)$order['total'];

        $items = $conn->prepare(
            'SELECT 
                oi.flower_id,
                oi.variety_id,
                oi.quantity,
                oi.price,
                f.name,
                f.emoji,
                v.variety_name
             FROM order_items oi
             INNER JOIN flowers f ON f.id = oi.flower_id
             INNER JOIN flower_varieties v ON v.id = oi.variety_id
             WHERE oi.order_id = ?'
        );

        $items->bind_param('i', $order['id']);
        $items->execute();

        $order['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    json_response([
        'success' => true,
        'orders' => $orders
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown orders action.'
], 404);
?>
