<?php
require "db.php";

header("Content-Type: application/json");

$action = $_GET["action"] ?? "";
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please login first"
    ]);
    exit;
}

$user_id = intval($_SESSION["user_id"]);

/* GET CART ITEMS */
if ($action === "get") {
    $sql = "
        SELECT 
            c.id AS cart_id,
            c.flower_id,
            c.quantity,
            f.name,
            f.price
        FROM cart c
        INNER JOIN flowers f ON c.flower_id = f.id
        WHERE c.user_id = ?
        ORDER BY c.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode([
        "success" => true,
        "items" => $items
    ]);
    exit;
}

/* ADD ITEM */
if ($action === "add") {
    $flower_id = intval($data["flower_id"] ?? $data["id"] ?? 0);
    $quantity = intval($data["quantity"] ?? 1);

    if ($flower_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid flower."
        ]);
        exit;
    }

    if ($quantity < 1) {
        $quantity = 1;
    }

    $flowerCheck = $conn->prepare("SELECT id FROM flowers WHERE id = ?");
    $flowerCheck->bind_param("i", $flower_id);
    $flowerCheck->execute();

    if (!$flowerCheck->get_result()->fetch_assoc()) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid flower."
        ]);
        exit;
    }

    $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND flower_id = ?");
    $check->bind_param("ii", $user_id, $flower_id);
    $check->execute();

    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        $newQty = intval($existing["quantity"]) + $quantity;

        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $newQty, $existing["id"], $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, flower_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $flower_id, $quantity);
        $stmt->execute();
    }

    echo json_encode([
        "success" => true,
        "message" => "Item added to cart"
    ]);
    exit;
}

/* UPDATE QUANTITY */
if ($action === "update") {
    $cart_id = intval($data["cart_id"] ?? 0);
    $quantity = intval($data["quantity"] ?? 1);

    if ($cart_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Cart ID is missing"
        ]);
        exit;
    }

    if ($quantity < 1) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();

        echo json_encode([
            "success" => true,
            "message" => "Item removed from cart"
        ]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Cart updated"
    ]);
    exit;
}

/* DELETE ITEM */
if ($action === "remove") {
    $cart_id = intval($data["cart_id"] ?? 0);

    if ($cart_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Cart ID is missing"
        ]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Item removed from cart"
    ]);
    exit;
}

/* CLEAR CART */
if ($action === "clear") {
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Cart cleared"
    ]);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Invalid cart action"
]);
exit;
?>