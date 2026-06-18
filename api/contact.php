<?php
require_once 'db.php';

$action = $_GET['action'] ?? '';
$data = request_data();

function ensure_contact_messages_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            subject VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('new','read','archived') DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
}

ensure_contact_messages_table($conn);

if ($action === 'submit') {
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        json_response([
            'success' => false,
            'message' => 'Please fill in all contact form fields.'
        ], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response([
            'success' => false,
            'message' => 'Please enter a valid email address.'
        ], 422);
    }

    if (strlen($name) > 100 || strlen($email) > 150 || strlen($subject) > 150) {
        json_response([
            'success' => false,
            'message' => 'Name, email, or subject is too long.'
        ], 422);
    }

    $stmt = $conn->prepare(
        'INSERT INTO contact_messages (user_id, name, email, subject, message)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $name, $email, $subject, $message);
    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Thank you. Your message has been received.',
        'id' => $conn->insert_id
    ]);
}

if ($action === 'list') {
    require_admin();

    $result = $conn->query(
        'SELECT cm.id, cm.user_id, cm.name, cm.email, cm.subject, cm.message, cm.status, cm.created_at,
                u.name AS user_name
         FROM contact_messages cm
         LEFT JOIN users u ON u.id = cm.user_id
         ORDER BY cm.id DESC'
    );

    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = $row['user_id'] === null ? null : (int)$row['user_id'];
        $messages[] = $row;
    }

    json_response([
        'success' => true,
        'messages' => $messages
    ]);
}

if ($action === 'update-status') {
    require_admin();

    $id = (int)($data['id'] ?? 0);
    $status = strtolower(trim($data['status'] ?? ''));
    $allowed = ['new', 'read', 'archived'];

    if ($id <= 0 || !in_array($status, $allowed, true)) {
        json_response([
            'success' => false,
            'message' => 'Invalid message status.'
        ], 422);
    }

    $stmt = $conn->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Message updated.'
    ]);
}

if ($action === 'delete') {
    require_admin();

    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        json_response([
            'success' => false,
            'message' => 'Invalid message id.'
        ], 422);
    }

    $stmt = $conn->prepare('DELETE FROM contact_messages WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_response([
        'success' => true,
        'message' => 'Message deleted.'
    ]);
}

json_response([
    'success' => false,
    'message' => 'Unknown contact action.'
], 404);
?>
