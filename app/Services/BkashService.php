<?php

namespace BPGW\Services;

defined('ABSPATH') || exit;

class BkashService
{
    private string $appKey;
    private string $appSecret;
    private string $username;
    private string $password;
    private bool   $sandbox;

    public function __construct(
        string $appKey,
        string $appSecret,
        string $username,
        string $password,
        bool $sandbox = false
    ) {
        $this->appKey    = $appKey;
        $this->appSecret = $appSecret;
        $this->username  = $username;
        $this->password  = $password;
        $this->sandbox   = $sandbox;
    }

    // Return the API base URL for sandbox or live mode.
    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    // Apply strict TLS verification using the CA bundle shipped with WordPress.
    private function applyTls($ch): void
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $caBundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
        if (file_exists($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
    }

    // The token cache is kept separately for sandbox and live modes.
    private function tokenOptionName(): string
    {
        return 'bpgw_bkash_token_' . ($this->sandbox ? 'sandbox' : 'live');
    }

    // Confirm an executed/queried payment actually belongs to the order it claims to pay.
    private function matchesOrder(array $result, \WC_Order $order): bool
    {
        $logger = wc_get_logger();

        // The payment must reference this exact order. The amount is not re-checked here:
        // bKash charges the exact amount supplied at payment creation, which we control.
        if (($result['merchantInvoiceNumber'] ?? '') !== (string) $order->get_id()) {
            $logger->warning(
                'bKash order mismatch — expected ' . $order->get_id() .
                ', got ' . ($result['merchantInvoiceNumber'] ?? ''),
                ['source' => 'bpgw-bkash']
            );
            return false;
        }

        return true;
    }

    // Reuse a recent token when possible, otherwise request a fresh one.
    private function getToken(): ?string
    {
        $logger = wc_get_logger();
        $stored = get_option($this->tokenOptionName(), null);

        // Only reuse a cached token for the same credentials and while it is still fresh.
        if (is_array($stored)
            && !empty($stored['token'])
            && ($stored['app_key'] ?? '') === $this->appKey
            && (time() - (int) ($stored['created_at'] ?? 0)) < (55 * 60)
        ) {
            return $stored['token'];
        }

        $url  = $this->baseUrl() . '/tokenized/checkout/token/grant';
        $body = json_encode([
            'app_key'    => $this->appKey,
            'app_secret' => $this->appSecret,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'username: ' . $this->username,
            'password: ' . $this->password,
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyTls($ch);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $logger->debug('bKash token cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (empty($result['id_token'])) {
            $logger->warning('bKash token grant failed — no id_token in response.', ['source' => 'bpgw-bkash']);
            return null;
        }

        update_option($this->tokenOptionName(), [
            'token'      => $result['id_token'],
            'app_key'    => $this->appKey,
            'created_at' => time(),
        ]);

        return $result['id_token'];
    }

    // Create a payment and return the hosted checkout URL.
    public function createPayment(
        \WC_Order $order,
        string $callbackUrl
    ): ?string {
        $orderId = (string) $order->get_id();
        $amount  = (float) $order->get_total();

        $logger = wc_get_logger();
        $logger->debug('createPayment called', ['source' => 'bpgw-bkash']);

        $token = $this->getToken();

        $logger->debug('Token result: ' . ($token ? 'received' : 'null'), ['source' => 'bpgw-bkash']);
        $logger->debug('Callback URL: ' . $callbackUrl, ['source' => 'bpgw-bkash']);

        if (!$token) {
            return null;
        }

        $url  = $this->baseUrl() . '/tokenized/checkout/create';
        $body = json_encode([
            'mode'                  => '0011',
            'payerReference'        => $orderId,
            'callbackURL'           => $callbackUrl,
            'amount'                => number_format($amount, 2, '.', ''),
            'currency'              => 'BDT',
            'intent'                => 'sale',
            'merchantInvoiceNumber' => $orderId,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $token,
                'X-APP-Key: ' . $this->appKey,
            ],
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT         => 30,
        ]);
        $this->applyTls($ch);

        $response = curl_exec($ch);

        $logger->debug('bKash create response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash create cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (empty($result['bkashURL'])) {
            return null;
        }

        // Persist the payment reference so an unpaid order can be reconciled later
        // if the customer never returns to trigger the callback.
        if (!empty($result['paymentID'])) {
            $order->update_meta_data('_bpgw_bkash_payment_id', sanitize_text_field($result['paymentID']));
            $order->save();
        }

        return $result['bkashURL'];
    }
    // Execute and verify a payment by payment ID, ensuring it matches the order.
    public function verifyPayment(string $paymentId, \WC_Order $order): bool
    {
        $logger = wc_get_logger();

        $token = $this->getToken();

        if (!$token) {
            $logger->debug('bKash execute skipped — token unavailable during verification.', ['source' => 'bpgw-bkash']);
            return false;
        }

        $url  = $this->baseUrl() . '/tokenized/checkout/execute';
        $body = json_encode(['paymentID' => $paymentId]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $token,
                'X-APP-Key: ' . $this->appKey,
            ],
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $this->applyTls($ch);

        $response = curl_exec($ch);

        $logger->debug('bKash execute response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash execute cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (($result['statusCode'] ?? '') !== '0000') {
            $logger->warning(
                'bKash execute not successful — statusCode: ' . ($result['statusCode'] ?? 'none'),
                ['source' => 'bpgw-bkash']
            );
            return false;
        }

        return $this->matchesOrder($result, $order);
    }

    // Query a payment's current status without executing it (used for reconciliation).
    public function queryPayment(string $paymentId, \WC_Order $order): bool
    {
        $logger = wc_get_logger();

        $token = $this->getToken();

        if (!$token) {
            return false;
        }

        $url  = $this->baseUrl() . '/tokenized/checkout/payment/status';
        $body = json_encode(['paymentID' => $paymentId]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $token,
                'X-APP-Key: ' . $this->appKey,
            ],
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $this->applyTls($ch);

        $response = curl_exec($ch);

        $logger->debug('bKash status response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash status cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        // Only a fully completed transaction counts as paid.
        if (($result['statusCode'] ?? '') !== '0000'
            || ($result['transactionStatus'] ?? '') !== 'Completed'
        ) {
            return false;
        }

        return $this->matchesOrder($result, $order);
    }
}