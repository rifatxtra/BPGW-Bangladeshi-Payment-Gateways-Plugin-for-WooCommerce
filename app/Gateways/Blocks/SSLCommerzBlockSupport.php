<?php

namespace BPGW\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

class SSLCommerzBlockSupport extends AbstractPaymentMethodType
{
    protected $name = 'bpgw_sslcommerz';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_bpgw_sslcommerz_settings', []);
        $logger = wc_get_logger();
        $logger->debug('SSLCommerzBlockSupport initialize() — settings: ' . json_encode($this->settings), ['source' => 'bpgw-blocks']);
    }

    public function is_active(): bool
    {
        $logger = wc_get_logger();
        $logger->debug('SSLCommerzBlockSupport is_active() check — full settings: ' . json_encode($this->settings), ['source' => 'bpgw-blocks']);
        
        if (empty($this->settings)) {
            $logger->debug('SSLCommerzBlockSupport: settings is empty', ['source' => 'bpgw-blocks']);
            return false;
        }
        
        $enabled = $this->settings['enabled'] ?? null;
        $logger->debug('SSLCommerzBlockSupport: enabled value is "' . $enabled . '"', ['source' => 'bpgw-blocks']);
        
        $is_active = !empty($enabled) && $enabled === 'yes';
        $logger->debug('SSLCommerzBlockSupport is_active() result: ' . ($is_active ? 'true' : 'false'), ['source' => 'bpgw-blocks']);
        
        return $is_active;
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'bpgw-sslcommerz-blocks',
            BPGW_URL . 'assets/js/sslcommerz-block.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element'],
            BPGW_VERSION,
            true
        );

        wp_localize_script('bpgw-sslcommerz-blocks', 'bpgwSslcommerz', [
            'logo' => BPGW_URL . 'assets/images/sslcommerz-logo.png',
        ]);

        return ['bpgw-sslcommerz-blocks'];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->settings['title'] ?? 'SSLCommerz',
            'description' => 'Make payment using SSLCommerz. You will be redirected to SSLCommerz to complete the payment.',
            'logo'        => BPGW_URL . 'assets/images/sslcommerz-logo.png',
        ];
    }
}
