<?php

namespace BPGW\Gateways;

defined('ABSPATH') || exit;

class BkashGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id    = 'bpgw_bkash';
        $this->title = 'bKash';
        $this->init_settings();
    }
    public function process_payment($order_id): array
    {
        return ['result' => 'fail'];
    }
}
