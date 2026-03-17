<?php

namespace BPGW\Gateways;

defined('ABSPATH') || exit;

class SSLCommerzGateway extends \WC_Payment_Gateway
{
    public string $store_id       = '';
    public string $store_password = '';

    public function __construct()
    {
        $this->id                 = 'bpgw_sslcommerz';
        $this->method_title       = 'SSLCommerz';
        $this->method_description = 'Make payment using SSLCommerz. You will be redirected to SSLCommerz to complete the payment.';
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title', 'SSLCommerz');
        $this->enabled        = $this->get_option('enabled');
        $this->store_id       = $this->get_option('store_id');
        $this->store_password = $this->get_option('store_password');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
    }

    public function process_payment($order_id): array
    {
        $order   = wc_get_order($order_id);
        $sandbox = get_option('bpgw_sandbox_mode', false);

        $service = new \BPGW\Services\SSLCommerzService(
            $this->store_id,
            $this->store_password,
            $sandbox
        );

        $callbackUrl = add_query_arg([
            'wc-api'   => 'bpgw_sslcommerz_callback',
            'order_id' => $order_id,
        ], home_url('/'));

        $result = $service->createPayment(
            (string) $order_id,
            (float)  $order->get_total(),
            $callbackUrl,
            $order
        );

        if (!$result || $result['result'] !== 'success') {
            $message = $result['message'] ?? 'Unable to initiate SSLCommerz payment. Please try again.';
            wc_add_notice('Payment error: ' . $message, 'error');
            return ['result' => 'failure', 'redirect' => ''];
        }

        return [
            'result'   => 'success',
            'redirect' => $result['redirect'],
        ];
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable SSLCommerz Payment',
                'default' => 'no',
            ],
            'title' => [
                'title'   => 'Title',
                'type'    => 'text',
                'default' => 'SSLCommerz',
            ],
            'store_id' => [
                'title'   => 'Store ID',
                'type'    => 'text',
                'default' => '',
            ],
            'store_password' => [
                'title'   => 'Store Password',
                'type'    => 'password',
                'default' => '',
            ],
        ];
    }
}