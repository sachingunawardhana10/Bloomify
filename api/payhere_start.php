<?php
require_once 'db.php';
require_once 'payhere_config.php';

header('Content-Type: text/html; charset=utf-8');

$userId = require_login();

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    die('Invalid order ID.');
}

if (
    PAYHERE_MERCHANT_ID === 'YOUR_SANDBOX_MERCHANT_ID' ||
    PAYHERE_MERCHANT_SECRET === 'YOUR_SANDBOX_MERCHANT_SECRET'
) {
    die('PayHere Sandbox Merchant ID and Merchant Secret are not configured.');
}

$stmt = $conn->prepare(
    "SELECT 
        o.id,
        o.total,
        o.payment_method,
        o.payment_status,
        u.name AS customer_name,
        u.email
     FROM orders o
     INNER JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?
     LIMIT 1"
);

$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Order not found.');
}

if ($order['payment_method'] !== 'PayHere Online') {
    die('This order is not an online payment order.');
}

if ($order['payment_status'] === 'Paid') {
    header('Location: ' . BLOOMIFY_PUBLIC_URL . '/frontEnd/payment-return.html?order_id=' . $orderId);
    exit;
}

$amount = (float)$order['total'];
$hash = payhere_hash((string)$orderId, $amount);

$nameParts = explode(' ', trim($order['customer_name']), 2);
$firstName = $nameParts[0] ?? 'Bloomify';
$lastName = $nameParts[1] ?? 'Customer';

$returnUrl = BLOOMIFY_PUBLIC_URL . '/frontEnd/payment-return.html?order_id=' . $orderId;
$cancelUrl = BLOOMIFY_PUBLIC_URL . '/frontEnd/payment-cancel.html?order_id=' . $orderId;
$notifyUrl = BLOOMIFY_PUBLIC_URL . '/api/payhere_notify.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting to Payment — Bloomify</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff8f2;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .box {
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            max-width: 460px;
        }

        h2 {
            margin-bottom: 10px;
            color: #c84f73;
        }

        button {
            margin-top: 20px;
            border: none;
            background: #c84f73;
            color: white;
            padding: 13px 22px;
            border-radius: 999px;
            font-weight: bold;
            cursor: pointer;
        }

        small {
            display: block;
            margin-top: 14px;
            color: #777;
        }
    </style>
</head>
<body>
<div class="box">
    <h2>Redirecting to Secure Payment</h2>
    <p>Please wait. You are being redirected to PayHere Sandbox.</p>

    <form method="post" action="<?php echo htmlspecialchars(payhere_checkout_url()); ?>" id="payhere-form">
        <input type="hidden" name="merchant_id" value="<?php echo htmlspecialchars(PAYHERE_MERCHANT_ID); ?>">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($returnUrl); ?>">
        <input type="hidden" name="cancel_url" value="<?php echo htmlspecialchars($cancelUrl); ?>">
        <input type="hidden" name="notify_url" value="<?php echo htmlspecialchars($notifyUrl); ?>">

        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
        <input type="hidden" name="items" value="Bloomify Order #<?php echo htmlspecialchars($orderId); ?>">
        <input type="hidden" name="currency" value="<?php echo htmlspecialchars(PAYHERE_CURRENCY); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars(number_format($amount, 2, '.', '')); ?>">

        <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>">
        <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($order['email']); ?>">
        <input type="hidden" name="phone" value="0771234567">
        <input type="hidden" name="address" value="Negombo">
        <input type="hidden" name="city" value="Negombo">
        <input type="hidden" name="country" value="Sri Lanka">

        <input type="hidden" name="hash" value="<?php echo htmlspecialchars($hash); ?>">

        <button type="submit">Continue to PayHere</button>
    </form>

    <small>Order #<?php echo htmlspecialchars($orderId); ?> | Amount: Rs. <?php echo htmlspecialchars(number_format($amount, 2)); ?></small>
</div>

<script>
    document.getElementById('payhere-form').submit();
</script>
</body>
</html>