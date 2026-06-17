<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'all';

try {
    if ($action === 'all' || $action === 'list' || $action === '') {
        $sql = "
            SELECT 
                id, 
                name, 
                emoji, 
                image, 
                price, 
                meaning, 
                tag, 
                stock
            FROM flowers
            ORDER BY id ASC
        ";

        $result = $conn->query($sql);

        $products = [];

        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];
            $row['in_stock'] = $row['stock'] > 0;

            if (empty($row['image'])) {
                $row['image'] = 'images/flowers/default.jpg';
            }

            $products[] = $row;
        }

        json_response([
            'success' => true,
            'products' => $products
        ]);
    }

    json_response([
        'success' => false,
        'message' => 'Unknown products action.'
    ], 404);

} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Products API failed.',
        'error' => $e->getMessage()
    ], 500);
}
?>