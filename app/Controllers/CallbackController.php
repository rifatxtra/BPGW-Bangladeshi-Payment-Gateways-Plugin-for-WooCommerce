<?php

namespace BPGW\Controllers;

defined('ABSPATH') || exit;

class CallbackController
{
    public static function handleBkash(): void
    {
        $order_id   = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $paymentId  = isset($_GET['paymentID']) ? sanitize_text_field($_GET['paymentID']) : '';
        $statusRaw  = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $status     = strtolower($statusRaw);
        $signature  = isset($_GET['signature']) ? sanitize_text_field($_GET['signature']) : '';
        $apiVersion = isset($_GET['apiVersion']) ? sanitize_text_field($_GET['apiVersion']) : '';

        $logger = wc_get_logger();
        $logger->debug(
            'bKash callback received — order_id: ' . $order_id .
            ' | status: ' . $status .
            ' | apiVersion: ' . $apiVersion,
            ['source' => 'bpgw-bkash']
        );

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->warning('bKash callback failed — invalid order_id: ' . $order_id, ['source' => 'bpgw-bkash']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($order->get_payment_method() !== 'bpgw_bkash') {
            $logger->warning(
                'bKash callback skipped — payment method mismatch for order ' . $order_id,
                ['source' => 'bpgw-bkash']
            );
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($order->is_paid()) {
            $logger->debug('bKash callback ignored — order already paid: ' . $order_id, ['source' => 'bpgw-bkash']);
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }

        // Treat explicit cancel or failure statuses as failed payments.
        if (in_array($status, ['cancel', 'canceled', 'cancelled', 'failure', 'failed'], true)) {
            $order->update_status('failed', 'bKash payment cancelled or failed.');
            wp_redirect($order->get_cancel_order_url());
            exit;
        }

        if ($status !== 'success' || empty($paymentId)) {
            $order->update_status('failed', 'bKash payment failed — invalid callback data.');
            wp_redirect($order->get_cancel_order_url());
            exit;
        }

        $gateway = new \BPGW\Gateways\BkashGateway();
        $sandbox = get_option('bpgw_sandbox_mode', false);

        $service = new \BPGW\Services\BkashService(
            $gateway->app_key,
            $gateway->app_secret,
            $gateway->username,
            $gateway->password,
            $sandbox
        );

        $verified = $service->verifyPayment($paymentId, $order);

        if ($verified) {
            $order->payment_complete($paymentId);
            $order->add_order_note('bKash payment completed. Payment ID: ' . $paymentId);
            $logger->debug('bKash payment verified — order ' . $order_id . ' marked complete.', ['source' => 'bpgw-bkash']);
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            $order->update_status('failed', 'bKash payment verification failed.');
            $logger->debug('bKash payment verification failed — order ' . $order_id, ['source' => 'bpgw-bkash']);
            wp_redirect($order->get_cancel_order_url());
        }

        exit;
    }

    public static function handleSSLCommerz(): void
    {
        // Read callback parameters from the query string.
        $order_id  = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $tran_id   = isset($_GET['tran_id']) ? sanitize_text_field($_GET['tran_id']) : '';
        $statusRaw = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $status    = strtolower($statusRaw);

        $logger = wc_get_logger();
        $logger->debug(
            'SSLCommerz callback received — order_id: ' . $order_id . ' | tran_id: ' . $tran_id . ' | status: ' . $status,
            ['source' => 'bpgw-sslcommerz']
        );

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->warning('SSLCommerz callback failed — invalid order_id: ' . $order_id, ['source' => 'bpgw-sslcommerz']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($order->get_payment_method() !== 'bpgw_sslcommerz') {
            $logger->warning(
                'SSLCommerz callback skipped — payment method mismatch for order ' . $order_id,
                ['source' => 'bpgw-sslcommerz']
            );
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($order->is_paid()) {
            $logger->debug('SSLCommerz callback ignored — order already paid: ' . $order_id, ['source' => 'bpgw-sslcommerz']);
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }

        if (in_array($status, ['failed', 'failure', 'cancel', 'canceled', 'cancelled'], true)) {
            $order->update_status('failed', 'SSLCommerz payment cancelled or failed.');
            wp_redirect($order->get_cancel_order_url());
            exit;
        }

        if (empty($tran_id)) {
            $order->update_status('failed', 'SSLCommerz payment failed — no transaction ID.');
            wp_redirect($order->get_cancel_order_url());
            exit;
        }

        $gateway = new \BPGW\Gateways\SSLCommerzGateway();
        $sandbox = get_option('bpgw_sandbox_mode', false);

        $service = new \BPGW\Services\SSLCommerzService(
            $gateway->store_id,
            $gateway->store_password,
            $sandbox
        );

        $verified = $service->verifyPayment($tran_id, $order);

        if ($verified) {
            $order->payment_complete($tran_id);
            $order->add_order_note('SSLCommerz payment completed. Transaction ID: ' . $tran_id);
            $logger->debug('SSLCommerz payment verified — order ' . $order_id . ' marked complete.', ['source' => 'bpgw-sslcommerz']);
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            $order->update_status('failed', 'SSLCommerz payment verification failed.');
            $logger->debug('SSLCommerz payment verification failed — order ' . $order_id . ' with tran_id: ' . $tran_id, ['source' => 'bpgw-sslcommerz']);
            wp_redirect($order->get_cancel_order_url());
        }

        exit;
    }
}
