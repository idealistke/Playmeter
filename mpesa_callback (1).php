<?php
// mpesa_callback.php
// Safaricom calls this after customer confirms/declines payment.
// Must be public HTTPS. No HTML output — JSON only.

require_once 'config/database.php';

// Log raw payload for debugging (disable in production)
@mkdir(__DIR__ . '/logs', 0755, true);
$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/logs/mpesa_callback.log',
    date('Y-m-d H:i:s') . " — " . $raw . "\n", FILE_APPEND);

$data = json_decode($raw, true);
$body = $data['Body']['stkCallback'] ?? null;

if (!$body) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback']);
    exit;
}

$checkoutRequestId = $body['CheckoutRequestID'];
$resultCode        = (int) $body['ResultCode'];

$database = new Database();
$db       = $database->getConnection();

if ($resultCode === 0) {
    // Payment successful — extract receipt number and amount
    $items = $body['CallbackMetadata']['Item'] ?? [];
    $meta  = [];
    foreach ($items as $item) {
        $meta[$item['Name']] = $item['Value'] ?? null;
    }
    $transactionCode = $meta['MpesaReceiptNumber'] ?? null;
    $amountPaid      = $meta['Amount']             ?? null;

    // Update plays table
    // Columns confirmed in live DB:
    //   payment_status, mpesa_transaction_code, amount_paid, amount (migration),
    //   status (migration), paid_at (migration)
    $stmt = $db->prepare("
        UPDATE plays
        SET payment_status         = 'paid',
            status                 = 'ended',
            mpesa_transaction_code = :txn,
            amount_paid            = COALESCE(:amt,  amount_paid),
            amount                 = COALESCE(:amt2, amount),
            paid_at                = NOW()
        WHERE mpesa_checkout_request_id = :cri
    ");
    $stmt->execute([
        ':txn'  => $transactionCode,
        ':amt'  => $amountPaid,
        ':amt2' => $amountPaid,
        ':cri'  => $checkoutRequestId,
    ]);

    // Update machine revenue totals
    $db->prepare("
        UPDATE machines m
        JOIN (
            SELECT SUM(plays_count) AS pc, SUM(amount_paid) AS ap
            FROM plays
            WHERE mpesa_checkout_request_id = ?
        ) p ON 1=1
        SET m.total_plays   = m.total_plays   + p.pc,
            m.total_revenue = m.total_revenue + p.ap
        WHERE m.id = 1
    ")->execute([$checkoutRequestId]);

    // Also update sessions if one exists for this checkout
    $db->prepare("
        UPDATE sessions
        SET payment_status         = 'paid',
            mpesa_transaction_code = :txn,
            amount_paid            = COALESCE(:amt, amount_paid)
        WHERE mpesa_checkout_request_id = :cri
    ")->execute([
        ':txn' => $transactionCode,
        ':amt' => $amountPaid,
        ':cri' => $checkoutRequestId,
    ]);

} else {
    // Payment failed or cancelled by customer
    $db->prepare("
        UPDATE plays
        SET payment_status = 'failed',
            status         = 'ended'
        WHERE mpesa_checkout_request_id = ?
    ")->execute([$checkoutRequestId]);
}

// Always respond 200 to Safaricom
http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
