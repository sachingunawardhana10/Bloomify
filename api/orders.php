<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

function order_has_column(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function order_table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?"
    );

    $stmt->bind_param("s", $table);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function ensure_order_schema(mysqli $conn): void {
    if (!order_has_column($conn, 'orders', 'recipient_name')) {
        $conn->query("ALTER TABLE orders ADD COLUMN recipient_name VARCHAR(100) NULL");
    }

    if (!order_has_column($conn, 'orders', 'recipient_phone')) {
        $conn->query("ALTER TABLE orders ADD COLUMN recipient_phone VARCHAR(30) NULL");
    }

    if (!order_has_column($conn, 'orders', 'delivery_address')) {
        $conn->query("ALTER TABLE orders ADD COLUMN delivery_address TEXT NULL");
    }

    if (!order_has_column($conn, 'orders', 'delivery_area')) {
        $conn->query("ALTER TABLE orders ADD COLUMN delivery_area VARCHAR(100) NULL");
    }

    if (!order_has_column($conn, 'orders', 'delivery_date')) {
        $conn->query("ALTER TABLE orders ADD COLUMN delivery_date DATE NULL");
    }

    if (!order_has_column($conn, 'orders', 'delivery_time')) {
        $conn->query("ALTER TABLE orders ADD COLUMN delivery_time VARCHAR(100) NULL");
    }

    if (!order_has_column($conn, 'orders', 'payment_method')) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash on Delivery'");
    }

    if (!order_has_column($conn, 'orders', 'payment_status')) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Unpaid'");
    }

    if (!order_has_column($conn, 'orders', 'payment_reference')) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(100) NULL");
    }

    if (!order_has_column($conn, 'orders', 'cod_collected_at')) {
        $conn->query("ALTER TABLE orders ADD COLUMN cod_collected_at DATETIME NULL");
    }

    if (!order_has_column($conn, 'orders', 'total')) {
        $conn->query("ALTER TABLE orders ADD COLUMN total DECIMAL(10,2) DEFAULT 0.00");
    }

    if (!order_has_column($conn, 'orders', 'status')) {
        $conn->query("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'pending'");
    }

    if (!order_has_column($conn, 'orders', 'notes')) {
        $conn->query("ALTER TABLE orders ADD COLUMN notes TEXT NULL");
    }

    if (!order_has_column($conn, 'orders', 'created_at')) {
        $conn->query("ALTER TABLE orders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    if (!order_table_exists($conn, 'order_items')) {
        $conn->query(
            "CREATE TABLE order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                flower_id INT NOT NULL,
                variety_id INT NULL,
                quantity INT NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    if (!order_has_column($conn, 'order_items', 'variety_id')) {
        $conn->query("ALTER TABLE order_items ADD COLUMN variety_id INT NULL AFTER flower_id");
    }

    if (!order_has_column($conn, 'order_items', 'price')) {
        $conn->query("ALTER TABLE order_items ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }

    if (!order_has_column($conn, 'cart', 'variety_id')) {
        $conn->query("ALTER TABLE cart ADD COLUMN variety_id INT NULL AFTER flower_id");
    }
}

ensure_order_schema($conn);

/* ================= PLACE ORDER ================= */
if ($action === 'place') {
    $transactionStarted = false;

    try {
        $notes = trim($data['notes'] ?? '');
        $paymentMethod = trim($data['payment_method'] ?? 'Cash on Delivery');

        $codDetails = $data['cod_details'] ?? [];

        $recipientName = trim($data['recipient_name'] ?? ($codDetails['recipient_name'] ?? ''));
        $recipientPhone = trim($data['recipient_phone'] ?? ($data['phone'] ?? ($codDetails['phone'] ?? '')));
        $deliveryAddress = trim($data['delivery_address'] ?? ($codDetails['address'] ?? ''));
        $deliveryArea = trim($data['delivery_area'] ?? ($data['city'] ?? ($codDetails['city'] ?? '')));
        $deliveryDate = trim($data['delivery_date'] ?? '');
        $deliveryTime = trim($data['delivery_time'] ?? ($codDetails['delivery_time'] ?? ''));

        if (!in_array($paymentMethod, ['Cash on Delivery', 'PayHere Online', 'Card Payment'], true)) {
            json_response([
                'success' => false,
                'message' => 'Invalid payment method.'
            ], 422);
        }

        if (
            $recipientName === '' ||
            $recipientPhone === '' ||
            $deliveryAddress === '' ||
            $deliveryDate === '' ||
            $deliveryTime === ''
        ) {
            json_response([
                'success' => false,
                'message' => 'Please fill all delivery details.'
            ], 422);
        }

        if ($deliveryDate < date('Y-m-d')) {
            json_response([
                'success' => false,
                'message' => 'Delivery date cannot be in the past.'
            ], 422);
        }

        $paymentStatus = $paymentMethod === 'Cash on Delivery' ? 'Unpaid' : 'Pending';
        $status = 'pending';

        $cartStmt = $conn->prepare(
            "SELECT
                c.id AS cart_id,
                c.flower_id,
                c.variety_id,
                c.quantity,

                f.name AS flower_name,
                f.price AS flower_price,
                f.stock AS flower_stock,

                fv.id AS real_variety_id,
                fv.name AS variety_name,
                fv.price AS variety_price,
                fv.stock AS variety_stock
             FROM cart c
             INNER JOIN flowers f ON f.id = c.flower_id
             LEFT JOIN flower_varieties fv ON fv.id = c.variety_id
             WHERE c.user_id = ?
             ORDER BY c.id ASC"
        );

        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();

        $cartItems = $cartStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($cartItems)) {
            json_response([
                'success' => false,
                'message' => 'Your cart is empty.'
            ], 422);
        }

        $total = 0.0;

        foreach ($cartItems as &$item) {
            $quantity = (int)$item['quantity'];
            $varietyId = $item['variety_id'] !== null ? (int)$item['variety_id'] : null;

            if ($varietyId !== null && empty($item['real_variety_id'])) {
                json_response([
                    'success' => false,
                    'message' => 'A selected flower colour no longer exists. Please remove it from cart.'
                ], 409);
            }

            $price = $varietyId !== null
                ? (float)$item['variety_price']
                : (float)$item['flower_price'];

            $stock = $varietyId !== null
                ? (int)$item['variety_stock']
                : (int)$item['flower_stock'];

            if ($stock <= 0) {
                json_response([
                    'success' => false,
                    'message' => $item['flower_name'] . ' is out of stock.'
                ], 409);
            }

            if ($quantity > $stock) {
                json_response([
                    'success' => false,
                    'message' => $item['flower_name'] . ' has only ' . $stock . ' in stock.'
                ], 409);
            }

            $item['order_price'] = $price;
            $item['order_stock'] = $stock;

            $total += $price * $quantity;
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        $orderStmt = $conn->prepare(
            "INSERT INTO orders
            (
                user_id,
                recipient_name,
                recipient_phone,
                delivery_address,
                delivery_area,
                delivery_date,
                delivery_time,
                payment_method,
                payment_status,
                total,
                status,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $orderStmt->bind_param(
            "issssssssdss",
            $userId,
            $recipientName,
            $recipientPhone,
            $deliveryAddress,
            $deliveryArea,
            $deliveryDate,
            $deliveryTime,
            $paymentMethod,
            $paymentStatus,
            $total,
            $status,
            $notes
        );

        $orderStmt->execute();

        $orderId = (int)$conn->insert_id;

        foreach ($cartItems as $item) {
            $flowerId = (int)$item['flower_id'];
            $varietyId = $item['variety_id'] !== null ? (int)$item['variety_id'] : null;
            $quantity = (int)$item['quantity'];
            $price = (float)$item['order_price'];

            $itemStmt = $conn->prepare(
                "INSERT INTO order_items
                (
                    order_id,
                    flower_id,
                    variety_id,
                    quantity,
                    price
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            $itemStmt->bind_param(
                "iiiid",
                $orderId,
                $flowerId,
                $varietyId,
                $quantity,
                $price
            );

            $itemStmt->execute();

            if ($varietyId !== null) {
                $stockStmt = $conn->prepare(
                    "UPDATE flower_varieties
                     SET stock = stock - ?
                     WHERE id = ?
                       AND flower_id = ?
                       AND stock >= ?"
                );

                $stockStmt->bind_param("iiii", $quantity, $varietyId, $flowerId, $quantity);
                $stockStmt->execute();

                if ($stockStmt->affected_rows <= 0) {
                    throw new Exception('Stock update failed for selected flower colour.');
                }
            } else {
                $stockStmt = $conn->prepare(
                    "UPDATE flowers
                     SET stock = stock - ?
                     WHERE id = ?
                       AND stock >= ?"
                );

                $stockStmt->bind_param("iii", $quantity, $flowerId, $quantity);
                $stockStmt->execute();

                if ($stockStmt->affected_rows <= 0) {
                    throw new Exception('Stock update failed for selected flower.');
                }
            }
        }

        $clearStmt = $conn->prepare(
            "DELETE FROM cart WHERE user_id = ?"
        );

        $clearStmt->bind_param("i", $userId);
        $clearStmt->execute();

        $conn->commit();

        json_response([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order_id' => $orderId,
            'total' => round($total, 2),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => $status
        ]);
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        json_response([
            'success' => false,
            'message' => 'Order failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}

/* ================= MY ORDERS ================= */
if ($action === 'mine') {
    try {
        $stmt = $conn->prepare(
            "SELECT
                id,
                recipient_name,
                recipient_phone,
                delivery_address,
                delivery_area,
                delivery_date,
                delivery_time,
                total,
                status,
                notes,
                payment_method,
                payment_status,
                payment_reference,
                cod_collected_at,
                created_at
             FROM orders
             WHERE user_id = ?
             ORDER BY id DESC"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($orders as &$order) {
            $order['id'] = (int)$order['id'];
            $order['total'] = (float)$order['total'];

            $itemsStmt = $conn->prepare(
                "SELECT
                    oi.flower_id,
                    oi.variety_id,
                    oi.quantity,
                    oi.price,
                    f.name AS flower_name,
                    f.emoji,
                    f.image,
                    COALESCE(fv.name, 'Standard') AS variety_name,
                    COALESCE(fv.color, '#c84f73') AS color
                 FROM order_items oi
                 LEFT JOIN flowers f ON f.id = oi.flower_id
                 LEFT JOIN flower_varieties fv ON fv.id = oi.variety_id
                 WHERE oi.order_id = ?"
            );

            $itemsStmt->bind_param("i", $order['id']);
            $itemsStmt->execute();

            $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items as &$item) {
                $item['flower_id'] = (int)$item['flower_id'];
                $item['variety_id'] = $item['variety_id'] !== null ? (int)$item['variety_id'] : null;
                $item['quantity'] = (int)$item['quantity'];
                $item['price'] = (float)$item['price'];
                $item['name'] = $item['flower_name'] ?: 'Deleted Flower';

                if (empty($item['emoji'])) {
                    $item['emoji'] = '🌸';
                }

                if (empty($item['image'])) {
                    $item['image'] = 'images/flowers/default.jpg';
                }
            }

            $order['items'] = $items;
        }

        json_response([
            'success' => true,
            'orders' => $orders
        ]);
    } catch (Throwable $e) {
        json_response([
            'success' => false,
            'message' => 'My orders failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}

json_response([
    'success' => false,
    'message' => 'Unknown orders action.'
], 404);
?>