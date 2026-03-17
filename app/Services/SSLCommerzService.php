<?php

namespace BPGW\Services;

defined('ABSPATH') || exit;

class SSLCommerzService
{
    private string $storeId;
    private string $storePassword;
    private bool   $sandbox;

    public function __construct(
        string $storeId,
        string $storePassword,
        bool $sandbox = false
    ) {
        $this->storeId       = $storeId;
        $this->storePassword = $storePassword;
        $this->sandbox       = $sandbox;
    }

    // Return the API base URL for sandbox or live mode.
    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    // Create a payment and return the hosted gateway URL.
    public function createPayment(
        string   $orderId,
        float    $amount,
        string   $callbackUrl,
        \WC_Order $order
    ): ?array {
        $logger = wc_get_logger();
        $logger->debug('SSLCommerz createPayment called', ['source' => 'bpgw-sslcommerz']);

        $transaction_id = 'SSLCZ_' . $orderId . '_' . uniqid();

        $post_data = [
            'store_id'       => $this->storeId,
            'store_passwd'   => $this->storePassword,
            'total_amount'   => number_format($amount, 2, '.', ''),
            'currency'       => 'BDT',
            'tran_id'        => $transaction_id,

            // Send all outcomes to one callback endpoint.
            'success_url'    => $callbackUrl . '&tran_id=' . $transaction_id,
            'fail_url'       => $callbackUrl . '&tran_id=' . $transaction_id,
            'cancel_url'     => $callbackUrl . '&tran_id=' . $transaction_id,

            // Customer data from WooCommerce order.
            'cus_name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'cus_email'      => $order->get_billing_email(),
            'cus_add1'       => $order->get_billing_address_1() ?: 'Dhaka',
            'cus_add2'       => $order->get_billing_address_2() ?: 'Dhaka',
            'cus_city'       => $order->get_billing_city() ?: 'Dhaka',
            'cus_state'      => $order->get_billing_state() ?: 'Dhaka',
            'cus_postcode'   => $order->get_billing_postcode() ?: '1000',
            'cus_country'    => $order->get_billing_country() ?: 'Bangladesh',
            'cus_phone'      => $order->get_billing_phone() ?: '01700000000',
            'cus_fax'        => $order->get_billing_phone() ?: '01700000000',

            // Shipping data (fallback to billing when missing).
            'ship_name'      => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
            'ship_add1'      => $order->get_shipping_address_1() ?: 'Dhaka',
            'ship_add2'      => $order->get_shipping_address_2() ?: 'Dhaka',
            'ship_city'      => $order->get_shipping_city() ?: 'Dhaka',
            'ship_state'     => $order->get_shipping_state() ?: 'Dhaka',
            'ship_postcode'  => $order->get_shipping_postcode() ?: '1000',
            'ship_country'   => $order->get_shipping_country() ?: 'Bangladesh',

            // Product metadata for gateway records.
            'product_name'   => 'Order #' . $orderId,
            'product_category' => 'General',
            'product_profile'  => 'general',

            'value_a' => $orderId,
        ];

        $api_url = $this->baseUrl() . '/gwprocess/v4/api.php';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $logger->debug('SSLCommerz create response: ' . $response, ['source' => 'bpgw-sslcommerz']);

        if (curl_errno($ch)) {
            $logger->error('SSLCommerz cURL error: ' . curl_error($ch), ['source' => 'bpgw-sslcommerz']);
            curl_close($ch);
            return ['result' => 'error', 'message' => 'Connection error'];
        }

        curl_close($ch);

        if ($http_code === 200 && $response) {
            $sslcz = json_decode($response, true);

            if (!empty($sslcz['GatewayPageURL'])) {
                // Save transaction ID on the order for traceability.
                $order->update_meta_data('_bpgw_sslcommerz_tran_id', $transaction_id);
                $order->save();

                return [
                    'result'   => 'success',
                    'redirect' => $sslcz['GatewayPageURL'],
                ];
            }

            $logger->warning('SSLCommerz no GatewayPageURL. Response: ' . $response, ['source' => 'bpgw-sslcommerz']);
            return [
                'result'  => 'error',
                'message' => $sslcz['failedreason'] ?? 'Payment initiation failed',
            ];
        }

        return ['result' => 'error', 'message' => 'HTTP ' . $http_code];
    }

    // Verify payment using the SSLCommerz transaction ID.
    public function verifyPayment(string $tran_id): bool
    {
        $validation_url  = $this->baseUrl() . '/validator/api/merchantTransIDvalidationAPI.php';
        $validation_url .= '?tran_id='     . urlencode($tran_id);
        $validation_url .= '&store_id='    . urlencode($this->storeId);
        $validation_url .= '&store_passwd='. urlencode($this->storePassword);
        $validation_url .= '&format=json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validation_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $logger = wc_get_logger();
        $logger->debug('SSLCommerz verify response: ' . $response, ['source' => 'bpgw-sslcommerz']);

        if (curl_errno($ch)) {
            $logger->error('SSLCommerz verify cURL error: ' . curl_error($ch), ['source' => 'bpgw-sslcommerz']);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);

            // Confirm SSLCommerz API responded successfully.
            if (isset($result['APIConnect']) && $result['APIConnect'] !== 'DONE') {
                $logger->warning(
                    'SSLCommerz API connection failed. Status: ' . $result['APIConnect'],
                    ['source' => 'bpgw-sslcommerz']
                );
                return false;
            }

            // Confirm transaction exists and has a valid status.
            if (isset($result['no_of_trans_found']) && $result['no_of_trans_found'] > 0) {
                $element = $result['element'][0];
                $status = $element['status'] ?? '';

                if (in_array($status, ['VALID', 'VALIDATED'])) {
                    $logger->debug(
                        'SSLCommerz payment verified. Transaction ID: ' . $tran_id . ' | Status: ' . $status,
                        ['source' => 'bpgw-sslcommerz']
                    );
                    return true;
                } else {
                    $logger->warning(
                        'SSLCommerz transaction status: ' . $status . ' | Error: ' . ($element['error'] ?? ''),
                        ['source' => 'bpgw-sslcommerz']
                    );
                }
            } else {
                $logger->warning(
                    'SSLCommerz no transaction found for ID: ' . $tran_id,
                    ['source' => 'bpgw-sslcommerz']
                );
            }
        } else {
            $logger->error(
                'SSLCommerz validation HTTP error. Code: ' . $http_code,
                ['source' => 'bpgw-sslcommerz']
            );
        }

        return false;
    }
}