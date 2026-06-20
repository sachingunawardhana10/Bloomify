<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'flowers';
$category = $_GET['category'] ?? 'All';
$subcategory = trim($_GET['subcategory'] ?? '');

try {
    // 'flowers' (used by app.js's hero teaser) and 'all' / 'list' / ''
    // (used by catalog.html / customize.html, plus aliases a teammate added)
    // all return the same shape, just under a different key.
    if ($action === 'flowers' || $action === 'all' || $action === 'list' || $action === '') {
        $imageColumnExists = false;
        $checkImage = $conn->query("SHOW COLUMNS FROM flowers LIKE 'image'");
        if ($checkImage && $checkImage->num_rows > 0) {
            $imageColumnExists = true;
        }

        $subcategoryColumnExists = false;
        $checkSubcategory = $conn->query("SHOW COLUMNS FROM flowers LIKE 'subcategory'");
        if ($checkSubcategory && $checkSubcategory->num_rows > 0) {
            $subcategoryColumnExists = true;
        }

        $imageSelect = $imageColumnExists
            ? 'f.image'
            : "'images/flowers/default.jpg' AS image";

        $subcategorySelect = $subcategoryColumnExists
            ? 'f.subcategory'
            : 'NULL AS subcategory';

        $where = [];
        $types = '';
        $params = [];

        if ($category !== 'All' && $category !== '') {
            $where[] = 'f.tag = ?';
            $types .= 's';
            $params[] = $category;
        }

        if ($subcategoryColumnExists && $subcategory !== '') {
            $where[] = 'f.subcategory = ?';
            $types .= 's';
            $params[] = $subcategory;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $varietiesTableExists = false;
        $checkVarieties = $conn->query("SHOW TABLES LIKE 'flower_varieties'");
        if ($checkVarieties && $checkVarieties->num_rows > 0) {
            $varietiesTableExists = true;
        }

        if ($varietiesTableExists) {
            $sql = "
                SELECT
                    f.id,
                    f.name,
                    f.emoji,
                    $imageSelect,
                    f.meaning,
                    f.tag,
                    $subcategorySelect,
                    v.id    AS variety_id,
                    v.variety_name,
                    v.color_hex,
                    v.price AS variety_price,
                    v.stock AS variety_stock
                FROM flowers f
                LEFT JOIN flower_varieties v ON v.flower_id = f.id
                $whereSql
                ORDER BY f.id ASC, v.price ASC
            ";
        } else {
            $sql = "
                SELECT
                    f.id,
                    f.name,
                    f.emoji,
                    $imageSelect,
                    f.meaning,
                    f.tag,
                    $subcategorySelect,
                    f.price,
                    f.stock
                FROM flowers f
                $whereSql
                ORDER BY f.id ASC
            ";
        }

        if ($where) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        $flowers = [];

        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['id'];
            $image = $row['image'] ?: 'images/flowers/default.jpg';
            $imagePath = __DIR__ . '/../frontEnd/' . ltrim((string)$image, '/\\');

            if (!is_file($imagePath)) {
                $image = 'images/flowers/default.jpg';
            }

            if (!isset($flowers[$id])) {
                $flowers[$id] = [
                    'id'          => $id,
                    'name'        => $row['name'],
                    'emoji'       => $row['emoji'],
                    'image'       => $image,
                    'meaning'     => $row['meaning'],
                    'tag'         => $row['tag'],
                    'subcategory' => $row['subcategory'],
                    'varieties'   => []
                ];
            }

            if ($varietiesTableExists) {
                if ($row['variety_id'] !== null) {
                    $flowers[$id]['varieties'][] = [
                        'id'    => (int)$row['variety_id'],
                        'name'  => $row['variety_name'],
                        'color' => $row['color_hex'],
                        'price' => (float)$row['variety_price'],
                        'stock' => (int)$row['variety_stock']
                    ];
                }
            } else {
                $flowers[$id]['varieties'][] = [
                    'id'    => 0,
                    'name'  => 'Standard',
                    'color' => '#FFFFFF',
                    'price' => (float)$row['price'],
                    'stock' => (int)$row['stock']
                ];
            }
        }

        if (isset($stmt)) {
            $stmt->close();
        }

        $flowers = array_values($flowers);

        // Keep top-level price/stock as aggregates so any code still reading
        // flower.price / flower.stock directly keeps working.
        foreach ($flowers as &$flower) {
            $prices = array_column($flower['varieties'], 'price');
            $stocks = array_column($flower['varieties'], 'stock');

            $flower['price']    = $prices ? min($prices) : 0;
            $flower['stock']    = $stocks ? array_sum($stocks) : 0;
            $flower['in_stock'] = $flower['stock'] > 0;
        }
        unset($flower);

        $responseKey = $action === 'flowers' ? 'data' : 'products';

        json_response([
            'success'    => true,
            $responseKey => $flowers
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
