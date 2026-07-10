<?php

namespace BPGW\Controllers;

defined('ABSPATH') || exit;

class AjaxController
{
    public static function saveSettings(): void
    {
        // Reject requests with an invalid nonce.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bpgw_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce.']);
            return;
        }

        // Only administrators may change gateway settings.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }

        // Persist the global sandbox toggle.
        $sandbox = isset($_POST['sandbox_mode']) && $_POST['sandbox_mode'] === '1';
        update_option('bpgw_sandbox_mode', $sandbox);

        wp_send_json_success(['message' => 'Settings saved.']);
    }
}