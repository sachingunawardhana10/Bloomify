<?php
// =======================================================
// PAYHERE CONFIGURATION
// Use Sandbox first. Do NOT use real payments while testing.
// =======================================================

define('PAYHERE_MODE', 'sandbox');

// Replace these with your PayHere Sandbox details.
define('PAYHERE_MERCHANT_ID', '1');
define('PAYHERE_MERCHANT_SECRET', 'ABCD');

// For normal localhost testing:
// return_url works, but notify_url will NOT work properly from PayHere.
// For real payment status update, use a public URL or tunnel.
define('BLOOMIFY_PUBLIC_URL', 'http://localhost/bloomify_FINAL/Bloomify');

define('PAYHERE_CURRENCY', 'LKR');

function payhere_checkout_url(): string {
    if (PAYHERE_MODE === 'live') {
        return 'https://www.payhere.lk/pay/checkout';
    }

    return 'https://sandbox.payhere.lk/pay/checkout';
}

function payhere_hash(string $orderId, float $amount): string {
    $amountFormatted = number_format($amount, 2, '.', '');

    return strtoupper(
        md5(
            PAYHERE_MERCHANT_ID .
            $orderId .
            $amountFormatted .
            PAYHERE_CURRENCY .
            strtoupper(md5(PAYHERE_MERCHANT_SECRET))
        )
    );
}

function payhere_verify_signature(
    string $merchantId,
    string $orderId,
    string $amount,
    string $currency,
    string $statusCode,
    string $receivedMd5sig
): bool {
    $localMd5sig = strtoupper(
        md5(
            $merchantId .
            $orderId .
            $amount .
            $currency .
            $statusCode .
            strtoupper(md5(PAYHERE_MERCHANT_SECRET))
        )
    );

    return hash_equals($localMd5sig, strtoupper($receivedMd5sig));
}
?>