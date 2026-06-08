<?php
require_once "db.php";
header("Content-Type: application/json");

// Public endpoint — no auth needed
$res  = $conn->query("SELECT id, name, emoji, price, meaning, tag, stock FROM flowers ORDER BY id ASC");

if (!$res) {
    echo json_encode(["success" => false, "message" => "Could not load products."]);
    exit;
}

$products = [];
while ($row = $res->fetch_assoc()) {
    $row['price']    = (float)$row['price'];
    $row['stock']    = (int)$row['stock'];
    $row['in_stock'] = $row['stock'] > 0;
    $products[]      = $row;
}

echo json_encode(["success" => true, "products" => $products]);
?>