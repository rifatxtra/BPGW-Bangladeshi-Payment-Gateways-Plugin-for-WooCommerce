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

    // Reuse a recent token when possible, otherwise request a fresh one.
    private function getToken(): ?string
    {
        $stored = get_option('bpgw_bkash_token', null);

        if ($stored) {
            $age = time() - $stored['created_at'];
            if ($age < (55 * 60)) {
                return $stored['token'];
            }
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        $logger = wc_get_logger();
        $logger->debug('bKash token URL: ' . $url, ['source' => 'bpgw-bkash']);
        $logger->debug('bKash token response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash token cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (empty($result['id_token'])) {
            return null;
        }

        update_option('bpgw_bkash_token', [
            'token'      => $result['id_token'],
            'created_at' => time(),
        ]);

        return $result['id_token'];
    }

    // Create a payment and return the hosted checkout URL.
    public function createPayment(
        string $orderId,
        float  $amount,
        string $callbackUrl
    ): ?string {
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
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT         => 30,
        ]);

        $response = curl_exec($ch);

        $logger->debug('bKash create response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash create cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        return $result['bkashURL'] ?? null;
    }
    // Execute and verify a payment by payment ID.
    public function verifyPayment(string $paymentId): bool
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);

        $logger->debug('bKash execute response: ' . $response, ['source' => 'bpgw-bkash']);

        if (curl_errno($ch)) {
            $logger->debug('bKash execute cURL error: ' . curl_error($ch), ['source' => 'bpgw-bkash']);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        return isset($result['statusCode']) && $result['statusCode'] === '0000';
    }
}