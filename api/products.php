<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'all';

function products_has_column(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function products_table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?"
    );

    $stmt->bind_param("s", $table);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function ensure_products_schema(mysqli $conn): void {
    if (!products_has_column($conn, 'flowers', 'emoji')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN emoji VARCHAR(20) DEFAULT '🌸' AFTER name");
    }

    if (!products_has_column($conn, 'flowers', 'image')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN image VARCHAR(255) DEFAULT 'images/flowers/default.jpg' AFTER emoji");
    }

    if (!products_has_column($conn, 'flowers', 'price')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00");
    }

    if (!products_has_column($conn, 'flowers', 'meaning')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN meaning TEXT NULL AFTER price");
    }

    if (!products_has_column($conn, 'flowers', 'tag')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN tag VARCHAR(100) NULL AFTER meaning");
    }

    if (!products_has_column($conn, 'flowers', 'stock')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN stock INT DEFAULT 20 AFTER tag");
    }

    if (!products_table_exists($conn, 'flower_varieties')) {
        $conn->query(
            "CREATE TABLE flower_varieties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                flower_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                color VARCHAR(30) DEFAULT '#c84f73',
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                stock INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
            )"
        );
    }
}

try {
    ensure_products_schema($conn);

    if ($action === 'all' || $action === 'list' || $action === '') {
        $result = $conn->query(
            "SELECT 
                id,
                name,
                emoji,
                image,
                price,
                meaning,
                tag,
                stock
             FROM flowers
             ORDER BY id ASC"
        );

        $products = [];

        while ($row = $result->fetch_assoc()) {
            $flowerId = (int)$row['id'];

            $row['id'] = $flowerId;
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];

            if (empty($row['emoji'])) {
                $row['emoji'] = '🌸';
            }

            if (empty($row['image'])) {
                $row['image'] = 'images/flowers/default.jpg';
            }

            $varietyStmt = $conn->prepare(
                "SELECT 
                    id,
                    name,
                    color,
                    price,
                    stock
                 FROM flower_varieties
                 WHERE flower_id = ?
                 ORDER BY id ASC"
            );

            $varietyStmt->bind_param("i", $flowerId);
            $varietyStmt->execute();

            $varieties = [];

            $varietyResult = $varietyStmt->get_result();

            while ($v = $varietyResult->fetch_assoc()) {
                $v['id'] = (int)$v['id'];
                $v['price'] = (float)$v['price'];
                $v['stock'] = (int)$v['stock'];

                if (empty($v['color'])) {
                    $v['color'] = '#c84f73';
                }

                $varieties[] = $v;
            }

            
            if (empty($varieties)) {
                $varieties[] = [
                    'id' => $flowerId,
                    'name' => 'Standard',
                    'color' => '#c84f73',
                    'price' => (float)$row['price'],
                    'stock' => (int)$row['stock']
                ];
            }

            $row['varieties'] = $varieties;
            $row['in_stock'] = array_sum(array_column($varieties, 'stock')) > 0;

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