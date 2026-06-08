<?php
require_once "db.php";
header("Content-Type: application/json");

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please log in to place orders."]);
    exit;
}

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents("php://input"), true) ?? [];

// ═══════════════════════════════════════════════
//  PLACE ORDER
// ═══════════════════════════════════════════════
if ($action === 'place') {
    $notes = trim($data['notes'] ?? '');

    // Pull cart from DB (never trust client prices)
    $stmt = $conn->prepare("
        SELECT c.flower_id, c.quantity, f.name, f.price, f.stock
        FROM cart c
        INNER JOIN flowers f ON f.id = c.flower_id
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($cart)) {
        echo json_encode(["success" => false, "message" => "Your cart is empty."]);
        exit;
    }

    // Validate stock for all items before touching DB
    foreach ($cart as $item) {
        if ((int)$item['stock'] < (int)$item['quantity']) {
            echo json_encode([
                "success" => false,
                "message" => "Sorry, {$item['name']} only has {$item['stock']} in stock."
            ]);
            exit;
        }
    }

    // Calculate total server-side
    $total = 0.0;
    foreach ($cart as $item) {
        $total += (float)$item['price'] * (int)$item['quantity'];
    }

    $conn->begin_transaction();
    try {
        // Insert order
        $ins = $conn->prepare("INSERT INTO orders (user_id, total, status, notes) VALUES (?, ?, 'pending', ?)");
        $ins->bind_param("ids", $user_id, $total, $notes);
        $ins->execute();
        $order_id = $conn->insert_id;

        // Insert order items & decrement stock
        foreach ($cart as $item) {
            $oi = $conn->prepare("INSERT INTO order_items (order_id, flower_id, quantity, price) VALUES (?, ?, ?, ?)");
            $oi->bind_param("iiid", $order_id, $item['flower_id'], $item['quantity'], $item['price']);
            $oi->execute();

            $up = $conn->prepare("UPDATE flowers SET stock = stock - ? WHERE id = ?");
            $up->bind_param("ii", $item['quantity'], $item['flower_id']);
            $up->execute();
        }

        // Clear cart
        $clr = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clr->bind_param("i", $user_id);
        $clr->execute();

        $conn->commit();

        echo json_encode([
            "success"  => true,
            "order_id" => $order_id,
            "total"    => round($total, 2),
            "message"  => "Order placed successfully!"
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Order failed. Please try again."]);
    }
    exit;
}

// ═══════════════════════════════════════════════
//  MY ORDERS (customer's own history)
// ═══════════════════════════════════════════════
if ($action === 'mine') {
    $stmt = $conn->prepare("
        SELECT id, total, status, notes, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get items for each order
    foreach ($orders as &$order) {
        $oi = $conn->prepare("
            SELECT oi.quantity, oi.price, f.name, f.emoji
            FROM order_items oi
            INNER JOIN flowers f ON f.id = oi.flower_id
            WHERE oi.order_id = ?
        ");
        $oi->bind_param("i", $order['id']);
        $oi->execute();
        $order['items'] = $oi->get_result()->fetch_all(MYSQLI_ASSOC);
        $order['total'] = (float)$order['total'];
    }

    echo json_encode(["success" => true, "orders" => $orders]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action."]);
?>