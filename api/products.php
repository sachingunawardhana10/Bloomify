<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'flowers';

try {

    // 'flowers' (used by app.js's hero teaser) and 'all' / 'list' / ''
    // (used by catalog.html / customize.html, plus aliases a teammate added)
    // all return the same shape, just under a different key.
    if ($action === 'flowers' || $action === 'all' || $action === 'list' || $action === '') {

        $sql = "
            SELECT
                f.id,
                f.name,
                f.emoji,
                f.image,
                f.meaning,
                f.tag,
                v.id    AS variety_id,
                v.variety_name,
                v.color_hex,
                v.price AS variety_price,
                v.stock AS variety_stock
            FROM flowers f
            LEFT JOIN flower_varieties v ON v.flower_id = f.id
            ORDER BY f.id ASC, v.price ASC
        ";

        $result = $conn->query($sql);

        $flowers = [];

        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['id'];

            if (!isset($flowers[$id])) {
                $flowers[$id] = [
                    'id'        => $id,
                    'name'      => $row['name'],
                    'emoji'     => $row['emoji'],
                    'image'     => $row['image'] ?: 'images/flowers/default.jpg',
                    'meaning'   => $row['meaning'],
                    'tag'       => $row['tag'],
                    'varieties' => []
                ];
            }

            if ($row['variety_id'] !== null) {
                $flowers[$id]['varieties'][] = [
                    'id'    => (int)$row['variety_id'],
                    'name'  => $row['variety_name'],
                    'color' => $row['color_hex'],
                    'price' => (float)$row['variety_price'],
                    'stock' => (int)$row['variety_stock']
                ];
            }
        }

        $flowers = array_values($flowers);

        // Keep top-level price/stock as aggregates so any code still reading
        // flower.price / flower.stock directly (e.g. the simple hero teaser
        // in app.js) keeps working without changes.
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