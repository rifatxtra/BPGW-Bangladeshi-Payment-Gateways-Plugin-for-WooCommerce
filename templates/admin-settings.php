<?php defined('ABSPATH') || exit;
$sandbox = get_option('bpgw_sandbox_mode', false);
?>

<div class="wrap bpgw-wrap">

    <h1 class="bpgw:text-xl bpgw:font-bold bpgw:mb-6">
        BPGW — Bangladeshi Payment Gateways
    </h1>

    <div class="bpgw:flex bpgw:flex-col bpgw:gap-6 bpgw:md:flex-row">

        <!-- Left: Settings -->
        <div class="bpgw:flex-1 bpgw:bg-white bpgw:p-6 bpgw:rounded bpgw:shadow">
            <h2 class="bpgw:text-lg bpgw:font-semibold bpgw:mb-4">General Settings</h2>

            <form id="bpgw-settings-form">

                <label class="bpgw:flex bpgw:items-center bpgw:gap-3 bpgw:cursor-pointer">
                    <input
                        type="checkbox"
                        id="bpgw_sandbox_mode"
                        name="bpgw_sandbox_mode"
                        value="1"
                        <?php checked($sandbox, true); ?>
                    >
                    <span class="bpgw:font-medium">Enable Sandbox Mode</span>
                </label>

                <p class="bpgw:text-sm bpgw:text-gray-500 bpgw:mt-2">
                    When enabled, all gateways will use test/sandbox credentials.
                </p>

                <button
                    type="submit"
                    id="bpgw-save-btn"
                    class="bpgw:mt-6 bpgw:bg-blue-600 bpgw:text-white bpgw:px-5 bpgw:py-2 bpgw:rounded bpgw:cursor-pointer"
                >
                    Save Settings
                </button>

            </form>
        </div>

        <!-- Right: About -->
        <div class="bpgw:w-full bpgw:md:w-72 bpgw:bg-white bpgw:p-6 bpgw:rounded bpgw:shadow">

            <h2 class="bpgw:text-lg bpgw:font-semibold bpgw:mb-4">About</h2>

            <p class="bpgw:text-sm bpgw:mb-1">
                <strong>Developer:</strong> Md. Rashedul Islam
            </p>
            <p class="bpgw:text-sm bpgw:mb-4">
                <a href="https://rifatxtra.com" target="_blank" class="bpgw:text-blue-600">
                    rifatxtra.com
                </a>
            </p>

            <hr class="bpgw:my-4">

            <div class="bpgw:flex bpgw:flex-col bpgw:gap-3">
                <a href="https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce"
                   target="_blank"
                   class="bpgw:text-sm bpgw:text-blue-600">
                    ⭐ Star on GitHub
                </a>
                <a href="https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce/issues"
                   target="_blank"
                   class="bpgw:text-sm bpgw:text-blue-600">
                    🐛 Report an Issue
                </a>
                <a href="https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce/blob/main/CONTRIBUTING.md"
                   target="_blank"
                   class="bpgw:text-sm bpgw:text-blue-600">
                    🤝 Contribute
                </a>
            </div>

            <hr class="bpgw:my-4">

            <p class="bpgw:text-xs bpgw:text-gray-400">
                Version <?php echo esc_html(BPGW_VERSION); ?>
            </p>

        </div>

    </div>

</div>