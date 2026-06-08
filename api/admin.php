<?php
session_start();
require "db.php";
header("Content-Type: application/json");

/* SIMPLE ADMIN CHECK */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? '';

/* ================= STATS ================= */
if ($action === "stats") {

    $orders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc();
    $products = $conn->query("SELECT COUNT(*) as c FROM flowers")->fetch_assoc();
    $pending = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc();

    $revenue = $conn->query("SELECT COALESCE(SUM(total),0) as t FROM orders")->fetch_assoc();

    echo json_encode([
        "success" => true,
        "stats" => [
            "total_orders" => (int)$orders['c'],
            "total_products" => (int)$products['c'],
            "pending_orders" => (int)$pending['c'],
            "revenue" => (float)$revenue['t'],
            "orders_this_month" => (int)$orders['c'],
            "revenue_this_month" => (float)$revenue['t']
        ]
    ]);
    exit;
}

/* ================= ORDERS ================= */
if ($action === "orders") {

    $result = $conn->query("
        SELECT o.id, o.total, o.status, o.created_at,
               u.name AS customer_name,
               u.email AS customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ");

    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $row['items_summary'] = "Order items available in order_items table";
        $orders[] = $row;
    }

    echo json_encode(["success" => true, "orders" => $orders]);
    exit;
}

/* ================= USERS ================= */
if ($action === "users") {

    $res = $conn->query("SELECT id,name,email,role,created_at FROM users");

    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode(["success" => true, "users" => $users]);
    exit;
}

/* ================= PRODUCTS ================= */
/* ================= PRODUCTS ================= */
if ($action === "products") {

    $res = $conn->query("SELECT * FROM flowers ORDER BY id DESC");

    $products = [];

    while ($row = $res->fetch_assoc()) {
        $row['in_stock'] = ((int)$row['stock'] > 0);
        $products[] = $row;
    }

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);
    exit;
}

/* ================= ADD PRODUCT ================= */
if ($action === "add-product") {

    $data = json_decode(file_get_contents("php://input"), true);

    $stmt = $conn->prepare("
        INSERT INTO flowers (name, emoji, price, meaning, tag, stock)
        VALUES (?, ?, ?, ?, ?, 100)
    ");

    $stmt->bind_param(
        "ssdss",
        $data['name'],
        $data['emoji'],
        $data['price'],
        $data['meaning'],
        $data['tag']
    );

    $stmt->execute();

    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid action"]);