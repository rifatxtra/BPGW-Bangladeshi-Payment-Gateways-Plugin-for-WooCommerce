<?php

namespace BPGW\Gateways;

defined('ABSPATH') || exit;

class BkashGateway extends \WC_Payment_Gateway
{
    public string $app_key    = '';
    public string $app_secret = '';
    public string $username   = '';
    public string $password   = '';

    public function __construct()
    {
        $this->id                 = 'bpgw_bkash';
        $this->method_title       = 'bKash';
        $this->method_description = 'Make payment using bKash. You will be redirected to bKash to complete the payment.';
        $this->has_fields         = false;

        // Load settings form definition before reading saved values.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'bKash');
        $this->enabled = $this->get_option('enabled');
        $this->app_key  = $this->get_option('app_key');
        $this->app_secret = $this->get_option('app_secret');
        $this->username = $this->get_option('username');
        $this->password = $this->get_option('password');
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
    }

    // Create a payment and return WooCommerce redirect data.
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        $sandbox = get_option('bpgw_sandbox_mode', false);

        $service = new \BPGW\Services\BkashService(
            $this->app_key,
            $this->app_secret,
            $this->username,
            $this->password,
            $sandbox
        );

        $callbackUrl = add_query_arg([
            'wc-api' => 'bpgw_bkash_callback',
            'order_id' => $order_id,
        ], home_url('/'));

        $bkashUrl = $service->createPayment(
            (string) $order_id,
            (float)  $order->get_total(),
            $callbackUrl
        );


        if (!$bkashUrl) {
            wc_add_notice('bKash payment failed. Please try again.', 'error');
            return ['result' => 'fail'];
        }

        return [
            'result'   => 'success',
            'redirect' => $bkashUrl,
        ];
    }


    // Define admin settings fields for the bKash gateway.
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable bKash Payment',
                'default' => 'no',
            ],
            'title' => [
                'title'   => 'Title',
                'type'    => 'text',
                'default' => 'bKash',
            ],
            'app_key' => [
                'title' => 'App Key',
                'type'  => 'text',
            ],
            'app_secret' => [
                'title' => 'App Secret',
                'type'  => 'password',
            ],
            'username' => [
                'title' => 'Username',
                'type'  => 'text',
            ],
            'password' => [
                'title' => 'Password',
                'type'  => 'password',
            ],
        ];
    }
}
