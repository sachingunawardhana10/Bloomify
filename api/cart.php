<?php
require_once 'db.php';

$userId = require_login();
$action = $_GET['action'] ?? '';
$data = request_data();

function cart_has_column(mysqli $conn, string $table, string $column): bool {
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

function cart_table_exists(mysqli $conn, string $table): bool {
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

function ensure_cart_schema(mysqli $conn): void {
    if (!cart_has_column($conn, 'cart', 'variety_id')) {
        $conn->query("ALTER TABLE cart ADD COLUMN variety_id INT NULL AFTER flower_id");
    }
}

ensure_cart_schema($conn);


if ($action === 'add') {
    try {
        $flowerId = (int)($data['flower_id'] ?? 0);
        $varietyId = isset($data['variety_id']) && $data['variety_id'] !== null
            ? (int)$data['variety_id']
            : null;
        $quantity = (int)($data['quantity'] ?? 1);

        if ($flowerId <= 0 || $quantity <= 0) {
            json_response([
                'success' => false,
                'message' => 'Invalid cart item.'
            ], 422);
        }

        $flowerStmt = $conn->prepare(
            "SELECT id, name, price, stock
             FROM flowers
             WHERE id = ?"
        );

        $flowerStmt->bind_param("i", $flowerId);
        $flowerStmt->execute();

        $flower = $flowerStmt->get_result()->fetch_assoc();

        if (!$flower) {
            json_response([
                'success' => false,
                'message' => 'Flower not found.'
            ], 404);
        }

        $availableStock = (int)$flower['stock'];

        if ($varietyId !== null && cart_table_exists($conn, 'flower_varieties')) {
            $varietyStmt = $conn->prepare(
                "SELECT id, flower_id, name, price, stock
                 FROM flower_varieties
                 WHERE id = ? AND flower_id = ?"
            );

            $varietyStmt->bind_param("ii", $varietyId, $flowerId);
            $varietyStmt->execute();

            $variety = $varietyStmt->get_result()->fetch_assoc();

            if (!$variety) {
                json_response([
                    'success' => false,
                    'message' => 'Selected flower variety not found.'
                ], 404);
            }

            $availableStock = (int)$variety['stock'];
        }

        if ($availableStock <= 0) {
            json_response([
                'success' => false,
                'message' => 'This item is out of stock.'
            ], 409);
        }

        $existingStmt = $conn->prepare(
            "SELECT id, quantity
             FROM cart
             WHERE user_id = ?
               AND flower_id = ?
               AND (
                    (variety_id IS NULL AND ? IS NULL)
                    OR variety_id = ?
               )
             LIMIT 1"
        );

        $existingStmt->bind_param("iiii", $userId, $flowerId, $varietyId, $varietyId);
        $existingStmt->execute();

        $existing = $existingStmt->get_result()->fetch_assoc();

        $currentQty = $existing ? (int)$existing['quantity'] : 0;
        $newQty = $currentQty + $quantity;

        if ($newQty > $availableStock) {
            json_response([
                'success' => false,
                'message' => 'Only ' . $availableStock . ' items available in stock.'
            ], 409);
        }

        if ($existing) {
            $cartId = (int)$existing['id'];

            $update = $conn->prepare(
                "UPDATE cart
                 SET quantity = ?
                 WHERE id = ? AND user_id = ?"
            );

            $update->bind_param("iii", $newQty, $cartId, $userId);
            $update->execute();
        } else {
            $insert = $conn->prepare(
                "INSERT INTO cart
                 (user_id, flower_id, variety_id, quantity)
                 VALUES (?, ?, ?, ?)"
            );

            $insert->bind_param("iiii", $userId, $flowerId, $varietyId, $quantity);
            $insert->execute();
        }

        json_response([
            'success' => true,
            'message' => 'Added to cart.'
        ]);
    } catch (Throwable $e) {
        json_response([
            'success' => false,
            'message' => 'Cart add failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}


if ($action === 'get') {
    try {
        $stmt = $conn->prepare(
            "SELECT
                c.id AS cart_id,
                c.user_id,
                c.flower_id,
                c.variety_id,
                c.quantity,

                f.name AS flower_name,
                f.emoji,
                f.image,

                COALESCE(fv.name, 'Standard') AS variety_name,
                COALESCE(fv.color, '#c84f73') AS color,
                COALESCE(fv.price, f.price) AS price,
                COALESCE(fv.stock, f.stock) AS stock
             FROM cart c
             INNER JOIN flowers f ON f.id = c.flower_id
             LEFT JOIN flower_varieties fv ON fv.id = c.variety_id
             WHERE c.user_id = ?
             ORDER BY c.id DESC"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $items = [];
        $total = 0;

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['cart_id'] = (int)$row['cart_id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['flower_id'] = (int)$row['flower_id'];
            $row['variety_id'] = $row['variety_id'] !== null ? (int)$row['variety_id'] : null;
            $row['quantity'] = (int)$row['quantity'];
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];

            if (empty($row['emoji'])) {
                $row['emoji'] = '🌸';
            }

            if (empty($row['image'])) {
                $row['image'] = 'images/flowers/default.jpg';
            }

            $row['name'] = $row['flower_name'];
            $row['line_total'] = $row['price'] * $row['quantity'];

            $total += $row['line_total'];

            $items[] = $row;
        }

        json_response([
            'success' => true,
            'items' => $items,
            'cart' => $items,
            'total' => round($total, 2)
        ]);
    } catch (Throwable $e) {
        json_response([
            'success' => false,
            'message' => 'Cart load failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}


if ($action === 'count') {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity), 0) AS count_value
         FROM cart
         WHERE user_id = ?"
    );

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    json_response([
        'success' => true,
        'count' => (int)$row['count_value']
    ]);
}


if ($action === 'update') {
    $cartId = (int)($data['cart_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);

    if ($cartId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Cart ID is missing.'
        ], 422);
    }

    if ($quantity <= 0) {
        $delete = $conn->prepare(
            "DELETE FROM cart WHERE id = ? AND user_id = ?"
        );

        $delete->bind_param("ii", $cartId, $userId);
        $delete->execute();

        json_response([
            'success' => true,
            'message' => 'Item removed.'
        ]);
    }

    $update = $conn->prepare(
        "UPDATE cart
         SET quantity = ?
         WHERE id = ? AND user_id = ?"
    );

    $update->bind_param("iii", $quantity, $cartId, $userId);
    $update->execute();

    json_response([
        'success' => true,
        'message' => 'Cart updated.'
    ]);
}

if ($action === 'remove' || $action === 'delete') {
    $cartId = (int)($data['cart_id'] ?? $data['id'] ?? 0);

    if ($cartId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Cart ID is missing.'
        ], 422);
    }

    $delete = $conn->prepare(
        "DELETE FROM cart WHERE id = ? AND user_id = ?"
    );

    $delete->bind_param("ii", $cartId, $userId);
    $delete->execute();

    json_response([
        'success' => true,
        'message' => 'Item removed.'
    ]);
}


if ($action === 'clear') {
    $delete = $conn->prepare(
        "DELETE FROM cart WHERE user_id = ?"
    );

    $delete->bind_param("i", $userId);
    $delete->execute();

    json_response([
        'success' => true,
        'message' => 'Cart cleared.'
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown cart action.'
], 404);
?>