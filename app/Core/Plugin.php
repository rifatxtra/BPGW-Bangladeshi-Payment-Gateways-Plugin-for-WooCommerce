<?php

namespace BPGW\Core;

defined('ABSPATH') || exit;

class Plugin
{

    private static ?Plugin $instance = null;

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();
        self::$instance->registerHooks();
    }


    private function registerHooks(): void
    {
        // Register all payment gateways.
        add_filter('woocommerce_payment_gateways', [GatewayManager::class, 'register']);

        // Register callback endpoint for bKash.
        add_action(
            'woocommerce_api_bpgw_bkash_callback',
            [\BPGW\Controllers\CallbackController::class, 'handleBkash']
        );

        // Register callback endpoint for SSLCommerz.
        add_action(
            'woocommerce_api_bpgw_sslcommerz_callback',
            [\BPGW\Controllers\CallbackController::class, 'handleSSLCommerz']
        );

        // Register Checkout Block support for both gateways.
        add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
            $registry->register(new \BPGW\Gateways\Blocks\BkashBlockSupport());
            $registry->register(new \BPGW\Gateways\Blocks\SSLCommerzBlockSupport());
        });

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            add_action('wp_ajax_bpgw_save_settings', [\BPGW\Controllers\AjaxController::class, 'saveSettings']);
        }
    }


    // Add the plugin settings page to the admin menu.
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'BPGW Settings',
            'BPGW',
            'manage_options',
            'bpgw-settings',
            [$this, 'renderAdminPage'],
            'dashicons-money-alt',
            58
        );
    }


    // Load admin assets only on the plugin settings page.
    public function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_bpgw-settings') {
            return;
        }

        wp_enqueue_style(
            'bpgw-admin',
            BPGW_URL . 'assets/css/style.css',
            [],
            BPGW_VERSION
        );

        wp_enqueue_script(
            'bpgw-admin',
            BPGW_URL . 'assets/js/admin.js',
            [],
            BPGW_VERSION,
            true
        );

        wp_localize_script('bpgw-admin', 'BPGW', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('bpgw_admin_nonce'),
        ]);
    }

    // Render the plugin settings page.
    public function renderAdminPage(): void
    {
        include BPGW_PATH . 'templates/admin-settings.php';
    }
}
