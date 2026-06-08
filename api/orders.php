<?php
session_start();
require "db.php";
header("Content-Type: application/json");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false]);
    exit;
}

if ($_GET['action'] === "checkout") {

    $conn->begin_transaction();

    try {

        $items = $conn->query("
            SELECT ci.*, f.price
            FROM cart_items ci
            JOIN flowers f ON ci.flower_id=f.id
            WHERE ci.user_id=$user_id
        ");

        $total = 0;
        $cart = [];

        while ($row = $items->fetch_assoc()) {
            $total += $row['price'] * $row['quantity'];
            $cart[] = $row;
        }

        $conn->query("INSERT INTO orders(user_id,total,status)
                      VALUES($user_id,$total,'pending')");

        $order_id = $conn->insert_id;

        foreach ($cart as $c) {

            $conn->query("
                INSERT INTO order_items(order_id,flower_id,quantity,price)
                VALUES($order_id,$c[flower_id],$c[quantity],$c[price])
            ");
        }

        $conn->query("DELETE FROM cart_items WHERE user_id=$user_id");

        $conn->commit();

        echo json_encode(["success" => true, "order_id" => $order_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false]);
    }

    exit;
}
?>