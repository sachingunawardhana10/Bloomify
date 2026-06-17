<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'all';

try {
    if ($action === 'all' || $action === 'list' || $action === '') {

        // Check whether image column exists.
        // This prevents 500 error if your database is not updated yet.
        $imageColumnExists = false;

        $checkImage = $conn->query("SHOW COLUMNS FROM flowers LIKE 'image'");
        if ($checkImage && $checkImage->num_rows > 0) {
            $imageColumnExists = true;
        }

        $imageSelect = $imageColumnExists
            ? "image"
            : "'images/flowers/default.jpg' AS image";

        $sql = "
            SELECT 
                id,
                name,
                emoji,
                $imageSelect,
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