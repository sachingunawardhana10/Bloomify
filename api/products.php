<?php
require "db.php";
header("Content-Type: application/json");

$res = $conn->query("SELECT * FROM flowers");

$data = [];

while ($row = $res->fetch_assoc()) {
    $row['in_stock'] = $row['stock'] > 0;
    $data[] = $row;
}

echo json_encode([
    "success" => true,
    "products" => $data
]);
?>