<?php

namespace BPGW\Controllers;

defined('ABSPATH') || exit;

class AjaxController
{
    public static function saveSettings(): void
    {
        // Reject requests with an invalid nonce.
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bpgw_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce.']);
            return;
        }

        // Persist the global sandbox toggle.
        $sandbox = isset($_POST['sandbox_mode']) && $_POST['sandbox_mode'] === '1';
        update_option('bpgw_sandbox_mode', $sandbox);

        wp_send_json_success(['message' => 'Settings saved.']);
    }
}