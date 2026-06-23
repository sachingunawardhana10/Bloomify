<?php
require_once 'db.php';

$action = $_GET['action'] ?? '';
$data = request_data();

function auth_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS count_value
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return (int)$row['count_value'] > 0;
}

function ensure_auth_user_status_column(mysqli $conn): void {
    if (!auth_column_exists($conn, 'users', 'is_active')) {
        $conn->query('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role');
    }
}

ensure_auth_user_status_column($conn);

if ($action === 'login') {
    $email = strtolower(trim($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(['success' => false, 'message' => 'Email and password are required.'], 422);
    }

    $stmt = $conn->prepare('SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    $stored = (string)$user['password'];
    $isHash = password_get_info($stored)['algo'] !== 0;
    $valid = $isHash ? password_verify($password, $stored) : hash_equals($stored, $password);

    if (!$valid) {
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    if (strtolower($user['role']) === 'customer' && (int)$user['is_active'] !== 1) {
        json_response(['success' => false, 'message' => 'Your account is deactivated. Please contact Bloomify support.'], 403);
    }

    if (!$isHash) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $up->bind_param('si', $newHash, $user['id']);
        $up->execute();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = strtolower($user['role']);

    json_response([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => strtolower($user['role'])
        ]
    ]);
}

if ($action === 'register') {
    $name = trim($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        json_response(['success' => false, 'message' => 'All fields are required.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'message' => 'Enter a valid email address.'], 422);
    }

    if (strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
    }

    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        json_response(['success' => false, 'message' => 'This email is already registered.'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $role = 'customer';

    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $name, $email, $hash, $role);
    $stmt->execute();

    json_response(['success' => true, 'message' => 'Account created. Please sign in.']);
}

if ($action === 'check') {
    $loggedIn = !empty($_SESSION['user_id']);

    json_response([
        'success' => true,
        'logged_in' => $loggedIn,
        'user' => $loggedIn ? [
            'id' => (int)$_SESSION['user_id'],
            'name' => $_SESSION['name'] ?? '',
            'role' => strtolower($_SESSION['role'] ?? 'customer')
        ] : null
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    json_response(['success' => true, 'message' => 'Logged out.']);
}

json_response(['success' => false, 'message' => 'Unknown auth action.'], 404);
?>
