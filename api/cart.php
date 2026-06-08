<?php
require_once "db.php";
header("Content-Type: application/json");

// ── Auth guard ──────────────────────────────────────
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please log in to use the cart."]);
    exit;
}

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents("php://input"), true) ?? [];

// ═══════════════════════════════════════════════
//  GET CART
// ═══════════════════════════════════════════════
if ($action === 'get') {
    $stmt = $conn->prepare("
        SELECT
            c.id        AS cart_item_id,
            c.flower_id,
            c.quantity,
            f.name,
            f.emoji,
            f.price,
            f.stock,
            (f.price * c.quantity) AS subtotal
        FROM cart c
        INNER JOIN flowers f ON f.id = c.flower_id
        WHERE c.user_id = ?
        ORDER BY c.id ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = [];
    $total = 0.0;
    $count = 0;
    foreach ($rows as $r) {
        $r['price']    = (float)$r['price'];
        $r['subtotal'] = (float)$r['subtotal'];
        $r['quantity'] = (int)$r['quantity'];
        $r['stock']    = (int)$r['stock'];
        $total += $r['subtotal'];
        $count += $r['quantity'];
        $items[] = $r;
    }

    echo json_encode([
        "success" => true,
        "items"   => $items,
        "total"   => round($total, 2),
        "count"   => $count
    ]);
    exit;
}

// ═══════════════════════════════════════════════
//  COUNT ONLY (for nav badge refresh)
// ═══════════════════════════════════════════════
if ($action === 'count') {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS cnt FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    echo json_encode(["success" => true, "count" => $cnt]);
    exit;
}

// ═══════════════════════════════════════════════
//  ADD TO CART
// ═══════════════════════════════════════════════
if ($action === 'add') {
    $flower_id = (int)($data['flower_id'] ?? 0);
    $qty       = max(1, (int)($data['quantity'] ?? 1));

    if ($flower_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid flower."]);
        exit;
    }

    // Stock check
    $fl = $conn->prepare("SELECT stock, name FROM flowers WHERE id = ? LIMIT 1");
    $fl->bind_param("i", $flower_id);
    $fl->execute();
    $flower = $fl->get_result()->fetch_assoc();

    if (!$flower) {
        echo json_encode(["success" => false, "message" => "Flower not found."]);
        exit;
    }
    if ($flower['stock'] < 1) {
        echo json_encode(["success" => false, "message" => "{$flower['name']} is out of stock."]);
        exit;
    }

    // Check existing cart item
    $ex = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND flower_id = ? LIMIT 1");
    $ex->bind_param("ii", $user_id, $flower_id);
    $ex->execute();
    $existing = $ex->get_result()->fetch_assoc();

    if ($existing) {
        $newQty = $existing['quantity'] + $qty;
        $up = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $up->bind_param("ii", $newQty, $existing['id']);
        $up->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO cart (user_id, flower_id, quantity) VALUES (?, ?, ?)");
        $ins->bind_param("iii", $user_id, $flower_id, $qty);
        $ins->execute();
    }

    // Return fresh count
    $cnt_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS cnt FROM cart WHERE user_id = ?");
    $cnt_stmt->bind_param("i", $user_id);
    $cnt_stmt->execute();
    $count = (int)$cnt_stmt->get_result()->fetch_assoc()['cnt'];

    echo json_encode(["success" => true, "message" => "Added to cart!", "count" => $count]);
    exit;
}

// ═══════════════════════════════════════════════
//  UPDATE QUANTITY
// ═══════════════════════════════════════════════
if ($action === 'update') {
    $flower_id = (int)($data['flower_id'] ?? 0);
    $qty       = (int)($data['quantity'] ?? 0);

    if ($flower_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid flower."]);
        exit;
    }

    if ($qty <= 0) {
        // Remove item
        $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND flower_id = ?");
        $del->bind_param("ii", $user_id, $flower_id);
        $del->execute();
    } else {
        $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND flower_id = ?");
        $upd->bind_param("iii", $qty, $user_id, $flower_id);
        $upd->execute();
    }

    // Return updated cart
    $stmt2 = $conn->prepare("
        SELECT c.flower_id, c.quantity, f.name, f.emoji, f.price, f.stock,
               (f.price * c.quantity) AS subtotal
        FROM cart c
        INNER JOIN flowers f ON f.id = c.flower_id
        WHERE c.user_id = ?
        ORDER BY c.id ASC
    ");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    $items2 = []; $total2 = 0.0; $count2 = 0;
    foreach ($rows2 as $r) {
        $r['price'] = (float)$r['price'];
        $r['subtotal'] = (float)$r['subtotal'];
        $r['quantity'] = (int)$r['quantity'];
        $total2 += $r['subtotal'];
        $count2 += $r['quantity'];
        $items2[] = $r;
    }

    echo json_encode(["success" => true, "items" => $items2, "total" => round($total2, 2), "count" => $count2]);
    exit;
}

// ═══════════════════════════════════════════════
//  REMOVE ITEM
// ═══════════════════════════════════════════════
if ($action === 'remove') {
    $flower_id = (int)($data['flower_id'] ?? 0);
    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND flower_id = ?");
    $del->bind_param("ii", $user_id, $flower_id);
    $del->execute();
    echo json_encode(["success" => true]);
    exit;
}

// ═══════════════════════════════════════════════
//  CLEAR CART
// ═══════════════════════════════════════════════
if ($action === 'clear') {
    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action."]);
?>