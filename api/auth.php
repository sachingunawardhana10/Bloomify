<?php
session_start();

require "db.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

/* ================= LOGIN ================= */
if ($action === "login") {

    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if ($email === '' || $password === '') {
        echo json_encode([
            "success" => false,
            "message" => "Missing credentials"
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid credentials"
        ]);
        exit;
    }

    // IMPORTANT: supports both bcrypt + plain hash fallback
    $valid =
        password_verify($password, $user['password']) ||
        hash('sha256', $password) === $user['password'] ||
        $password === $user['password'];

    if (!$valid) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid credentials"
        ]);
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];

    echo json_encode([
        "success" => true,
        "user" => [
            "id" => $user['id'],
            "role" => $user['role'],
            "name" => $user['name']
        ]
    ]);
    exit;
}

/* ================= REGISTER ================= */
if ($action === "register") {

    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        echo json_encode([
            "success" => false,
            "message" => "Missing fields"
        ]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users(name,email,password,role) VALUES (?,?,?,'customer')"
    );
    $stmt->bind_param("sss", $name, $email, $hashed);

    echo json_encode([
        "success" => $stmt->execute()
    ]);
    exit;
}

/* ================= CHECK ================= */
if ($action === "check") {

    echo json_encode([
        "logged_in" => isset($_SESSION['user_id']),
        "user" => [
            "id" => $_SESSION['user_id'] ?? null,
            "role" => $_SESSION['role'] ?? null,
            "name" => $_SESSION['name'] ?? null
        ]
    ]);
    exit;
}

/* ================= LOGOUT ================= */
if ($action === "logout") {
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Invalid action"
]);