<?php

namespace BPGW\Core;

defined('ABSPATH') || exit;

class Plugin
{

    private static ?Plugin $instance = null;
    //singleton instance
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
        //register gateways
        add_filter('woocommerce_payment_gateways', [GatewayManager::class, 'register']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            add_action('wp_ajax_bpgw_save_settings', [\BPGW\Controllers\AjaxController::class, 'saveSettings']);
        }
    }


    //register admin menu for plugin settings
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'BPGW Settings',  // Page title
            'BPGW',           // Sidebar label
            'manage_options', // Who can see it (admin only)
            'bpgw-settings',  // Slug used in URL
            [$this, 'renderAdminPage'], // Callback
            'dashicons-money-alt', // Icon
            58 // Position
        );
    }


    //enqueue admin assets for plugin settings page
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

    // render admin page for plugin settings
    public function renderAdminPage(): void
    {
        include BPGW_PATH . 'templates/admin-settings.php';
    }
}
