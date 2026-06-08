<?php
require_once "db.php";
header("Content-Type: application/json");

$action = $_GET['action'] ?? '';
$raw    = file_get_contents("php://input");
$data   = json_decode($raw, true) ?? [];

// ═══════════════════════════════════════════════
//  LOGIN
// ═══════════════════════════════════════════════
if ($action === "login") {

    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(["success"=>false,"message"=>"Invalid credentials"]);
        exit;
    }

    // SAFE PASSWORD CHECK (handles whitespace issues)
    if (trim($password) !== trim($user['password'])) {
        echo json_encode(["success"=>false,"message"=>"Invalid credentials"]);
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = strtolower($user['role']); // 🔥 normalize
    $_SESSION['name'] = $user['name'];

    echo json_encode([
        "success"=>true,
        "user"=>[
            "id"=>$user['id'],
            "name"=>$user['name'],
            "role"=>strtolower($user['role'])
        ]
    ]);
    exit;
}

// ═══════════════════════════════════════════════
//  REGISTER
// ═══════════════════════════════════════════════
if ($action === 'register') {
    $name     = trim($data['name'] ?? '');
    $email    = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    if (!$name || !$email || !$password) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email address."]);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(["success" => false, "message" => "Password must be at least 6 characters."]);
        exit;
    }

    // Check duplicate
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "This email is already registered."]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins  = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
    $ins->bind_param("sss", $name, $email, $hash);

    if ($ins->execute()) {
        echo json_encode(["success" => true, "message" => "Account created! You can now sign in."]);
    } else {
        echo json_encode(["success" => false, "message" => "Registration failed. Please try again."]);
    }
    exit;
}

// ═══════════════════════════════════════════════
//  CHECK SESSION
// ═══════════════════════════════════════════════
if ($action === 'check') {
    $loggedIn = isset($_SESSION['user_id']);
    echo json_encode([
        "logged_in" => $loggedIn,
        "user" => $loggedIn ? [
            "id"   => (int)$_SESSION['user_id'],
            "name" => $_SESSION['name'],
            "role" => $_SESSION['role']
        ] : null
    ]);
    exit;
}

// ═══════════════════════════════════════════════
//  LOGOUT
// ═══════════════════════════════════════════════
if ($action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action."]);
?>