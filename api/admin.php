<?php
require "db.php";
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? '';

/* STATS */
if ($action === "stats") {

    $orders = $conn->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'];
    $products = $conn->query("SELECT COUNT(*) c FROM flowers")->fetch_assoc()['c'];
    $revenue = $conn->query("SELECT COALESCE(SUM(total),0) t FROM orders")->fetch_assoc()['t'];

    echo json_encode([
        "success"=>true,
        "stats"=>[
            "orders"=>$orders,
            "products"=>$products,
            "revenue"=>$revenue
        ]
    ]);
    exit;
}

/* ORDERS */
if ($action === "orders") {

    $res = $conn->query("
        SELECT o.id, o.total, o.status, u.name
        FROM orders o
        JOIN users u ON u.id = o.user_id
        ORDER BY o.id DESC
    ");

    echo json_encode([
        "success"=>true,
        "orders"=>$res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}

/* PRODUCTS */
if ($action === "products") {

    $res = $conn->query("SELECT * FROM flowers ORDER BY id DESC");

    echo json_encode([
        "success"=>true,
        "products"=>$res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}


/* ADD PRODUCT */
if ($action === "add-product") {

    $data = json_decode(file_get_contents("php://input"), true);

    $name = $data['name'] ?? '';
    $emoji = $data['emoji'] ?? '';
    $price = $data['price'] ?? 0;
    $tag = $data['tag'] ?? '';
    $meaning = $data['meaning'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO flowers
        (name, emoji, price, tag, meaning)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => $conn->error
        ]);
        exit;
    }

    $stmt->bind_param(
        "ssdss",
        $name,
        $emoji,
        $price,
        $tag,
        $meaning
    );

    $success = $stmt->execute();

    echo json_encode([
        "success" => $success,
        "message" => $success ? "Product added" : $stmt->error
    ]);

    exit;
}

/* UPDATE PRODUCT */
if ($action === "update-product") {

    $data = json_decode(file_get_contents("php://input"), true);

    $stmt = $conn->prepare("
        UPDATE flowers
        SET name=?, image=?, price=?, tag=?, meaning=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssssi",
        $data['name'],
        $data['image'],
        $data['price'],
        $data['tag'],
        $data['meaning'],
        $data['id']
    );

    echo json_encode([
        "success" => $stmt->execute()
    ]);
    exit;
}

/* DELETE PRODUCT */
if ($action === "delete-product") {

    $id = $_GET['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM flowers WHERE id=?");
    $stmt->bind_param("i", $id);

    echo json_encode([
        "success" => $stmt->execute()
    ]);
    exit;
}

/* USERS */
if ($action === "users") {

    $res = $conn->query("SELECT id,name,email,role FROM users");

    echo json_encode([
        "success"=>true,
        "users"=>$res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}