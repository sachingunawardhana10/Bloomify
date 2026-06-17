<?php
require_once 'db.php';
require_once 'payhere_config.php';

$merchantId = $_POST['merchant_id'] ?? '';
$orderId = $_POST['order_id'] ?? '';
$paymentId = $_POST['payment_id'] ?? '';
$payhereAmount = $_POST['payhere_amount'] ?? '';
$payhereCurrency = $_POST['payhere_currency'] ?? '';
$statusCode = $_POST['status_code'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';
$method = $_POST['method'] ?? '';
$statusMessage = $_POST['status_message'] ?? '';

if (
    $merchantId === '' ||
    $orderId === '' ||
    $payhereAmount === '' ||
    $payhereCurrency === '' ||
    $statusCode === '' ||
    $md5sig === ''
) {
    http_response_code(400);
    echo 'Missing payment data.';
    exit;
}

if ($merchantId !== PAYHERE_MERCHANT_ID) {
    http_response_code(400);
    echo 'Invalid merchant.';
    exit;
}

$isValid = payhere_verify_signature(
    $merchantId,
    $orderId,
    $payhereAmount,
    $payhereCurrency,
    $statusCode,
    $md5sig
);

if (!$isValid) {
    http_response_code(400);
    echo 'Invalid signature.';
    exit;
}

$orderIdInt = (int)$orderId;

$stmt = $conn->prepare('SELECT id, total FROM orders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $orderIdInt);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$dbAmount = number_format((float)$order['total'], 2, '.', '');
$gatewayAmount = number_format((float)$payhereAmount, 2, '.', '');

if ($dbAmount !== $gatewayAmount) {
    http_response_code(400);
    echo 'Amount mismatch.';
    exit;
}

$paymentStatus = 'Pending';
$orderStatus = 'pending';

if ($statusCode == '2') {
    $paymentStatus = 'Paid';
    $orderStatus = 'processing';
} elseif ($statusCode == '0') {
    $paymentStatus = 'Pending';
    $orderStatus = 'pending';
} elseif ($statusCode == '-1') {
    $paymentStatus = 'Cancelled';
    $orderStatus = 'cancelled';
} elseif ($statusCode == '-2') {
    $paymentStatus = 'Failed';
    $orderStatus = 'cancelled';
} elseif ($statusCode == '-3') {
    $paymentStatus = 'Chargedback';
    $orderStatus = 'cancelled';
}

$conn->begin_transaction();

try {
    $paymentGateway = 'PayHere';
    $rawPayload = json_encode($_POST);

    $paymentStmt = $conn->prepare(
        "INSERT INTO payments 
        (
            order_id,
            payment_gateway,
            gateway_payment_id,
            amount,
            currency,
            method,
            status_code,
            status_message,
            md5sig,
            raw_payload
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            gateway_payment_id = VALUES(gateway_payment_id),
            amount = VALUES(amount),
            currency = VALUES(currency),
            method = VALUES(method),
            status_code = VALUES(status_code),
            status_message = VALUES(status_message),
            md5sig = VALUES(md5sig),
            raw_payload = VALUES(raw_payload),
            updated_at = CURRENT_TIMESTAMP"
    );

    $amountFloat = (float)$payhereAmount;

    $paymentStmt->bind_param(
        'issdssssss',
        $orderIdInt,
        $paymentGateway,
        $paymentId,
        $amountFloat,
        $payhereCurrency,
        $method,
        $statusCode,
        $statusMessage,
        $md5sig,
        $rawPayload
    );

    $paymentStmt->execute();

    $orderStmt = $conn->prepare(
        'UPDATE orders 
         SET payment_status = ?, payment_reference = ?, status = ?
         WHERE id = ?'
    );

    $orderStmt->bind_param(
        'sssi',
        $paymentStatus,
        $paymentId,
        $orderStatus,
        $orderIdInt
    );

    $orderStmt->execute();

    $conn->commit();

    http_response_code(200);
    echo 'Payment notification received.';
} catch (Throwable $e) {
    $conn->rollback();

    http_response_code(500);
    echo 'Payment update failed.';
}
?>