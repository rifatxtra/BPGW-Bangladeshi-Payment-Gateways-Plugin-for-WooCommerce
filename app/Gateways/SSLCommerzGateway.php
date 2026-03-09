<?php

namespace BPGW\Gateways;

defined('ABSPATH') || exit;

class SSLCommerzGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id    = 'bpgw_sslcommerz';
        $this->title = 'SSLCommerz';
        $this->init_settings();
    }
    public function process_payment($order_id): array
    {
        return ['result' => 'fail'];
    }
}
