<?php
session_start();
require "db.php";

header("Content-Type: application/json");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "Not logged in"
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

/* =========================
   GET CART
========================= */
if ($action === "get") {

    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.flower_id,
            c.quantity,
            f.name,
            f.price,
            f.image,
            f.stock
        FROM cart c
        JOIN flowers f ON c.flower_id = f.id
        WHERE c.user_id = ?
    ");

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

/* =========================
   ADD TO CART
========================= */
if ($action === "add") {

    $flower_id = intval($data['flower_id'] ?? 0);
    $qty = intval($data['quantity'] ?? 1);

    if ($flower_id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid flower_id"
        ]);
        exit;
    }

    // check existing item
    $stmt = $conn->prepare("
        SELECT id, quantity 
        FROM cart 
        WHERE user_id = ? AND flower_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $flower_id);
    $stmt->execute();

    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE cart 
            SET quantity = quantity + ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $qty, $existing['id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO cart (user_id, flower_id, quantity)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $user_id, $flower_id, $qty);
        $stmt->execute();
    }

    echo json_encode([
        "success" => true,
        "message" => "Added to cart"
    ]);
    exit;
}

/* =========================
   CLEAR CART
========================= */
if ($action === "clear") {

    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    echo json_encode(["success" => true]);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Invalid action"
]);
?>