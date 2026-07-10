<?php

/**
 * Plugin Name: BPGW - Bangladeshi Payment Gateways Plugin for WooCommerce
 * Plugin URI:  https://bpgw.rifatxtra.com/
 * Description: Bangladeshi payment gateways Plugin (bKash, SSLCommerz or others) for WooCommerce.
 * Version:     1.0.1
 * Author:      Md. Rashedul Islam
 * Author URI:  https://rifatxtra.com/
 * Text Domain: bpgw
 */



//if accessed directly, exit
defined('ABSPATH') || exit;

//define constants
define('BPGW_VERSION', '1.0.1');
define('BPGW_FILE',    __FILE__);
define('BPGW_PATH',    plugin_dir_path(__FILE__));
define('BPGW_URL',     plugin_dir_url(__FILE__));


//autoload classes
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'BPGW\\') !== 0) {
        return;
    }

    $relative = str_replace('BPGW\\', '', $class);
    $file      = BPGW_PATH . 'app/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


//clear scheduled reconciliation on deactivation
register_deactivation_hook(__FILE__, function (): void {
    $timestamp = wp_next_scheduled('bpgw_reconcile_pending');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'bpgw_reconcile_pending');
    }
});


//bootstrap the plugin
add_action('plugins_loaded', function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>BPGW:</strong> WooCommerce must be active.';
            echo '</p></div>';
        });
        return;
    }

    \BPGW\Core\Plugin::boot();
});

