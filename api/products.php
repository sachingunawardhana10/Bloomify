<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'all';
$data = request_data();

function products_table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?"
    );

    $stmt->bind_param("s", $table);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    return (int)$result['count_value'] > 0;
}

function products_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    return (int)$result['count_value'] > 0;
}

function ensure_flower_columns(mysqli $conn): void {
    if (!products_column_exists($conn, 'flowers', 'emoji')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN emoji VARCHAR(20) DEFAULT '🌸' AFTER name");
    }

    if (!products_column_exists($conn, 'flowers', 'image')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN image VARCHAR(255) DEFAULT 'images/flowers/default.jpg' AFTER emoji");
    }

    if (!products_column_exists($conn, 'flowers', 'meaning')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN meaning TEXT NULL AFTER price");
    }

    if (!products_column_exists($conn, 'flowers', 'tag')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN tag VARCHAR(100) NULL AFTER meaning");
    }

    if (!products_column_exists($conn, 'flowers', 'stock')) {
        $conn->query("ALTER TABLE flowers ADD COLUMN stock INT DEFAULT 20 AFTER tag");
    }
}

function ensure_variety_table(mysqli $conn): void {
    if (!products_table_exists($conn, 'flower_varieties')) {
        $conn->query(
            "CREATE TABLE flower_varieties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                flower_id INT NOT NULL,
                name VARCHAR(100) NOT NULL DEFAULT 'Standard',
                color VARCHAR(50) DEFAULT '#c84f73',
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                stock INT NOT NULL DEFAULT 20,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
            )"
        );
    }
}

if ($action === 'all') {
    try {
        ensure_flower_columns($conn);
        ensure_variety_table($conn);

        $flowerResult = $conn->query(
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

        if (!$flowerResult) {
            json_response([
                'success' => false,
                'message' => 'Flower query failed.',
                'error' => $conn->error
            ], 500);
        }

        $products = [];

        while ($flower = $flowerResult->fetch_assoc()) {
            $flowerId = (int)$flower['id'];

            $flower['id'] = $flowerId;
            $flower['price'] = (float)$flower['price'];
            $flower['stock'] = (int)$flower['stock'];

            if (empty($flower['emoji'])) {
                $flower['emoji'] = '🌸';
            }

            if (empty($flower['image'])) {
                $flower['image'] = 'images/flowers/default.jpg';
            }

            if ($flower['meaning'] === null) {
                $flower['meaning'] = '';
            }

            if ($flower['tag'] === null) {
                $flower['tag'] = 'Bloomify';
            }

            $varietyStmt = $conn->prepare(
                "SELECT 
                    id,
                    flower_id,
                    name,
                    color,
                    price,
                    stock
                 FROM flower_varieties
                 WHERE flower_id = ?
                 ORDER BY id ASC"
            );

            $varietyStmt->bind_param('i', $flowerId);
            $varietyStmt->execute();

            $varietyResult = $varietyStmt->get_result();
            $varieties = [];

            while ($variety = $varietyResult->fetch_assoc()) {
                $varieties[] = [
                    'id' => (int)$variety['id'],
                    'flower_id' => (int)$variety['flower_id'],
                    'name' => $variety['name'] ?: 'Standard',
                    'color' => $variety['color'] ?: '#c84f73',
                    'price' => (float)$variety['price'],
                    'stock' => (int)$variety['stock']
                ];
            }

            if (empty($varieties)) {
                $insertVariety = $conn->prepare(
                    "INSERT INTO flower_varieties
                    (
                        flower_id,
                        name,
                        color,
                        price,
                        stock
                    )
                    VALUES (?, 'Standard', '#c84f73', ?, ?)"
                );

                $insertVariety->bind_param(
                    'idi',
                    $flowerId,
                    $flower['price'],
                    $flower['stock']
                );

                $insertVariety->execute();

                $varieties[] = [
                    'id' => (int)$conn->insert_id,
                    'flower_id' => $flowerId,
                    'name' => 'Standard',
                    'color' => '#c84f73',
                    'price' => (float)$flower['price'],
                    'stock' => (int)$flower['stock']
                ];
            }

            $totalStock = 0;

            foreach ($varieties as $variety) {
                $totalStock += (int)$variety['stock'];
            }

            $flower['varieties'] = $varieties;
            $flower['stock'] = $totalStock;
            $flower['in_stock'] = $totalStock > 0;

            $products[] = $flower;
        }

        json_response([
            'success' => true,
            'products' => $products
        ]);
    } catch (Throwable $e) {
        json_response([
            'success' => false,
            'message' => 'Products failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}

json_response([
    'success' => false,
    'message' => 'Unknown products action.'
], 404);
?>