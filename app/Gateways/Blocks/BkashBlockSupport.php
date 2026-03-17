<?php

namespace BPGW\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

class BkashBlockSupport extends AbstractPaymentMethodType
{
    protected $name = 'bpgw_bkash';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_bpgw_bkash_settings', []);
        $logger = wc_get_logger();
        $logger->debug('BkashBlockSupport initialize() — settings: ' . json_encode($this->settings), ['source' => 'bpgw-blocks']);
    }

    public function is_active(): bool
    {
        $logger = wc_get_logger();
        $logger->debug('BkashBlockSupport is_active() check — full settings: ' . json_encode($this->settings), ['source' => 'bpgw-blocks']);
        
        if (empty($this->settings)) {
            $logger->debug('BkashBlockSupport: settings is empty', ['source' => 'bpgw-blocks']);
            return false;
        }
        
        $enabled = $this->settings['enabled'] ?? null;
        $logger->debug('BkashBlockSupport: enabled value is "' . $enabled . '"', ['source' => 'bpgw-blocks']);
        
        $is_active = !empty($enabled) && $enabled === 'yes';
        $logger->debug('BkashBlockSupport is_active() result: ' . ($is_active ? 'true' : 'false'), ['source' => 'bpgw-blocks']);
        
        return $is_active;
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'bpgw-bkash-blocks',
            BPGW_URL . 'assets/js/bkash-block.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element'],
            BPGW_VERSION,
            true
        );

        wp_localize_script('bpgw-bkash-blocks', 'bpgwBkash', [
            'logo' => BPGW_URL . 'assets/images/bkash-logo.png',
        ]);

        return ['bpgw-bkash-blocks'];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->settings['title'] ?? 'bKash',
            'description' => 'Make payment using bKash. You will be redirected to bKash to complete the payment.',
            'logo'        => BPGW_URL . 'assets/images/bkash-logo.png',
        ];
    }
}
