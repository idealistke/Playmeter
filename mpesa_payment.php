<?php
// mpesa_payment.php — Daraja API: STK Push + status query

// ── YOUR DARAJA CREDENTIALS ───────────────────────────────────────
define('MPESA_CONSUMER_KEY',    'ibYSZkGyTJhaCq160X3PCARTk8zDYa07HEUrHYvlyl4d0aIh');
define('MPESA_CONSUMER_SECRET', 'I39sJtfQIAoJFS81OnGfCQvHQvkwASYoQZZcXFgQAQjU1wIcrLG2SkbPcDiM7T49');
define('MPESA_SHORTCODE',       '174379');          // Replace with your Paybill/Till
define('MPESA_PASSKEY',         'YOUR_LIPA_NA_MPESA_PASSKEY');
define('MPESA_CALLBACK_URL',    'https://yourdomain.com/mpesa_callback.php'); // Must be HTTPS
define('MPESA_ENV',             'sandbox');         // 'sandbox' or 'production'
// ─────────────────────────────────────────────────────────────────

class MpesaPayment {

    private function baseUrl(): string {
        return MPESA_ENV === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /** Get OAuth token from Daraja */
    public function getAccessToken(): string|false {
        $url  = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $cred = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Basic $cred"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => MPESA_ENV === 'production',
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        return $data['access_token'] ?? false;
    }

    /**
     * Initiate STK Push to customer phone
     * @param string $phone   e.g. "0712345678" or "254712345678"
     * @param float  $amount  KSh amount (will be rounded to integer)
     * @param string $ref     Unique reference e.g. "FIFA26-42"
     */
    public function stkPush(string $phone, float $amount, string $ref): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not get M-Pesa access token.'];
        }

        // Normalise to 254XXXXXXXXX
        $phone     = preg_replace('/\s+/', '', $phone);
        $phone     = preg_replace('/^0/', '254', $phone);
        $amount    = (int) ceil($amount);
        $timestamp = date('YmdHis');
        $password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline', // use CustomerBuyGoodsOnline for Till
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => MPESA_SHORTCODE,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => MPESA_CALLBACK_URL,
            'AccountReference'  => $ref,
            'TransactionDesc'   => 'FIFA 26 Play - PlayMeter',
        ];

        $ch = curl_init($this->baseUrl() . '/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => MPESA_ENV === 'production',
            CURLOPT_TIMEOUT        => 20,
        ]);
        $res  = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);

        if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
            return [
                'success'             => true,
                'checkout_request_id' => $data['CheckoutRequestID'],
                'message'             => 'STK Push sent.',
            ];
        }

        return [
            'success' => false,
            'message' => $data['errorMessage']
                      ?? $data['ResponseDescription']
                      ?? 'STK Push failed.',
        ];
    }

    /** Query STK Push status (used when callback hasn't fired yet) */
    public function queryStatus(string $checkoutRequestId): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'paid' => false, 'message' => 'Token error'];
        }

        $timestamp = date('YmdHis');
        $password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $ch = curl_init($this->baseUrl() . '/mpesa/stkpushquery/v1/query');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => MPESA_ENV === 'production',
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);

        $code = (string) ($data['ResultCode'] ?? '');

        if ($code === '0') {
            return ['success' => true, 'paid' => true,  'message' => 'Payment confirmed'];
        } elseif ($code === '1032') {
            return ['success' => true, 'paid' => false, 'message' => 'Payment cancelled by customer'];
        } elseif ($code === '1037') {
            return ['success' => true, 'paid' => false, 'message' => 'Payment request timed out'];
        }

        // Still pending or unknown
        return ['success' => true, 'paid' => false, 'message' => 'Waiting for payment...'];
    }
}
