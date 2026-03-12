<?php

namespace BPGW\Gateways;

defined('ABSPATH') || exit;

class BkashGateway extends \WC_Payment_Gateway
{
    public function __construct()
{
    $this->id                 = 'bpgw_bkash';
    $this->method_title       = 'bKash';
    $this->method_description = 'Accept payments via bKash hosted payment gateway.';
    $this->has_fields         = false; // hosted flow — no fields on checkout page

    $this->init_form_fields(); // define settings fields first
    $this->init_settings();    // then load saved values

    $this->title = $this->get_option('title', 'bKash');
}
    public function process_payment($order_id): array
    {
        return ['result' => 'fail'];
    }
}
