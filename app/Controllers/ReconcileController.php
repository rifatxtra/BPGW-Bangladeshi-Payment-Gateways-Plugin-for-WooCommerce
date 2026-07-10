<?php

namespace BPGW\Controllers;

defined('ABSPATH') || exit;

class ReconcileController
{
    // Re-check recent unpaid orders whose gateway callback may never have arrived
    // (e.g. the customer paid but closed the browser before returning to the site).
    // Runs on a WP-Cron schedule.
    public static function run(): void
    {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $orders = wc_get_orders([
            'status'         => ['pending', 'on-hold'],
            'payment_method' => ['bpgw_bkash', 'bpgw_sslcommerz'],
            'date_created'   => '>' . (time() - DAY_IN_SECONDS),
            'limit'          => 50,
        ]);

        if (empty($orders)) {
            return;
        }

        $logger  = wc_get_logger();
        $sandbox = get_option('bpgw_sandbox_mode', false);

        foreach ($orders as $order) {
            if ($order->is_paid()) {
                continue;
            }

            switch ($order->get_payment_method()) {
                case 'bpgw_bkash':
                    self::reconcileBkash($order, $sandbox, $logger);
                    break;
                case 'bpgw_sslcommerz':
                    self::reconcileSSLCommerz($order, $sandbox, $logger);
                    break;
            }
        }
    }

    private static function reconcileBkash(\WC_Order $order, $sandbox, $logger): void
    {
        $paymentId = $order->get_meta('_bpgw_bkash_payment_id');
        if (!$paymentId) {
            return;
        }

        $gateway = new \BPGW\Gateways\BkashGateway();
        $service = new \BPGW\Services\BkashService(
            $gateway->app_key,
            $gateway->app_secret,
            $gateway->username,
            $gateway->password,
            $sandbox
        );

        // Try to finalise the payment; if it was already executed, fall back to a status query.
        $paid = $service->verifyPayment($paymentId, $order)
            || $service->queryPayment($paymentId, $order);

        if ($paid) {
            $order->payment_complete($paymentId);
            $order->add_order_note('bKash payment reconciled via scheduled check. Payment ID: ' . $paymentId);
            $logger->info('bKash order reconciled — ' . $order->get_id(), ['source' => 'bpgw-bkash']);
        }
    }

    private static function reconcileSSLCommerz(\WC_Order $order, $sandbox, $logger): void
    {
        $tranId = $order->get_meta('_bpgw_sslcommerz_tran_id');
        if (!$tranId) {
            return;
        }

        $gateway = new \BPGW\Gateways\SSLCommerzGateway();
        $service = new \BPGW\Services\SSLCommerzService(
            $gateway->store_id,
            $gateway->store_password,
            $sandbox
        );

        if ($service->verifyPayment($tranId, $order)) {
            $order->payment_complete($tranId);
            $order->add_order_note('SSLCommerz payment reconciled via scheduled check. Transaction ID: ' . $tranId);
            $logger->info('SSLCommerz order reconciled — ' . $order->get_id(), ['source' => 'bpgw-sslcommerz']);
        }
    }
}
