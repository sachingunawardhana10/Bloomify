<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'all';

if ($action === 'all' || $action === 'list' || $action === '') {
    $result = $conn->query('SELECT id, name, emoji, price, meaning, tag, stock FROM flowers ORDER BY id ASC');
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        $row['in_stock'] = $row['stock'] > 0;
        $products[] = $row;
    }

    json_response(['success' => true, 'products' => $products]);
}

json_response(['success' => false, 'message' => 'Unknown products action.'], 404);
?>