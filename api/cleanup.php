<?php
// Cleanup script to remove duplicate flowers
require_once "db.php";

// Remove all flowers with ID > 6 (the duplicates)
$stmt = $conn->prepare("DELETE FROM flowers WHERE id > 6");
$result = $stmt->execute();

if ($result) {
    echo json_encode(["success" => true, "message" => "Duplicate flowers removed"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to remove duplicates"]);
}
?>
