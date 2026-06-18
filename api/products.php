<?php
require_once 'db.php';


$action = $_GET['action'] ?? 'all';
$category = $_GET['category'] ?? 'All';
$subcategory = trim($_GET['subcategory'] ?? '');

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

        $subcategoryColumnExists = false;
        $checkSubcategory = $conn->query("SHOW COLUMNS FROM flowers LIKE 'subcategory'");
        if ($checkSubcategory && $checkSubcategory->num_rows > 0) {
            $subcategoryColumnExists = true;
        }

        $subcategorySelect = $subcategoryColumnExists
            ? "subcategory"
            : "NULL AS subcategory";

        $where = [];
        $types = '';
        $params = [];

        if ($category !== 'All' && !empty($category)) {
            $where[] = 'tag = ?';
            $types .= 's';
            $params[] = $category;
        }

        if ($subcategoryColumnExists && $subcategory !== '') {
            $where[] = 'subcategory = ?';
            $types .= 's';
            $params[] = $subcategory;
        }

        if ($where) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                id,
                name,
                emoji,
                $imageSelect,
                price,
                meaning,
                tag,
                $subcategorySelect,
                stock
            FROM flowers
            $whereSql
            ORDER BY id ASC
        ";
        $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            
            $sql = "
                SELECT 
                    id, 
                    name,
                    emoji,
                    $imageSelect, 
                    price,
                    meaning,
                    tag,
                    $subcategorySelect,
                    stock
                FROM flowers
                ORDER BY id ASC
            ";
            $result = $conn->query($sql);
        }

        $products = [];
        if ($result) {

        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];
            $row['in_stock'] = $row['stock'] > 0;

            $imagePath = __DIR__ . '/../frontEnd/' . ltrim((string)$row['image'], '/\\');
            if (empty($row['image']) || !is_file($imagePath)) {
                $row['image'] = 'images/flowers/default.jpg';
            }

            $products[] = $row;
        }

        }
        if (isset($stmt)) {
            $stmt->close();
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
