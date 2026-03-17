# BPGW — Developer Documentation

Complete technical reference for contributors and developers building on BPGW.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Directory Structure](#directory-structure)
- [Bootstrap & Lifecycle](#bootstrap--lifecycle)
- [Autoloader](#autoloader)
- [Core Classes](#core-classes)
- [Gateway Classes](#gateway-classes)
- [Service Classes](#service-classes)
- [Controller Classes](#controller-classes)
- [Payment Flows](#payment-flows)
- [Database Storage](#database-storage)
- [AJAX System](#ajax-system)
- [Asset Pipeline](#asset-pipeline)
- [Block Checkout Support](#block-checkout-support)
- [Adding a New Gateway](#adding-a-new-gateway)
- [Security](#security)
- [Local Development](#local-development)

---

## Architecture Overview

BPGW follows a Laravel-inspired architecture inside WordPress. The goal is clean separation of concerns — each class has one responsibility and delegates to others.

```
WordPress Hooks  →  Plugin.php (wires everything)
                        │
              ┌─────────┼──────────────┐
              ↓         ↓              ↓
        Gateways   Controllers      Services
     (WC adapter)  (HTTP handlers)  (API logic)
```

**Key principle:** Gateway classes talk to WooCommerce. Service classes talk to payment APIs. They never cross.

---

## Directory Structure

```
bangladesh-payment-gateways-woocommerce/
│
├── bangladesh-payment-gateways-woocommerce.php   ← Entry point
│
├── app/
│   ├── Core/
│   │   ├── Plugin.php              ← Service provider — all hooks registered here
│   │   └── GatewayManager.php      ← Gateway list registration
│   │
│   ├── Controllers/
│   │   ├── SettingsController.php  ← Admin page renderer
│   │   ├── AjaxController.php      ← AJAX request handlers
│   │   └── CallbackController.php  ← Payment callback handlers
│   │
│   ├── Services/
│   │   ├── BkashService.php        ← bKash API logic (token, create, verify)
│   │   └── SSLCommerzService.php   ← SSLCommerz API logic
│   │
│   ├── Gateways/
│   │   ├── BkashGateway.php        ← WC_Payment_Gateway for bKash
│   │   ├── SSLCommerzGateway.php   ← WC_Payment_Gateway for SSLCommerz
│   │   └── Blocks/
│   │       ├── BkashBlockSupport.php      ← Block checkout support for bKash
│   │       └── SSLCommerzBlockSupport.php ← Block checkout support for SSLCommerz
│   │
│   └── Helpers/                    ← Shared utility functions (future use)
│
├── assets/
│   ├── css/
│   │   └── style.css               ← Compiled Tailwind output (do not edit)
│   ├── js/
│   │   ├── admin.js                ← Admin settings JS (vanilla, no jQuery)
│   │   ├── bkash-block.js          ← bKash block checkout registration
│   │   └── sslcommerz-block.js     ← SSLCommerz block checkout registration
│   ├── images/
│   │   ├── bkash-logo.png          ← bKash logo
│   │   └── sslcommerz-logo.png     ← SSLCommerz logo
│   └── tailwind.css                ← Tailwind input file
│
├── templates/
│   └── admin-settings.php          ← Admin page HTML template
│
├── package.json                    ← npm build config
└── tailwind.config.js              ← Tailwind configuration
```

---

## Bootstrap & Lifecycle

### Entry Point

`bangladesh-payment-gateways-woocommerce.php` is the file WordPress discovers via its plugin header comment.

**Execution order:**

```
1. WordPress loads all plugins
2. plugins_loaded hook fires
3. WooCommerce existence check
4. Plugin::boot() called
5. Singleton created
6. registerHooks() wires all actions/filters
7. WordPress continues booting
8. Hooks fire at appropriate points
```

### Constants

Defined in the main plugin file:

| Constant | Value | Usage |
|---|---|---|
| `BPGW_VERSION` | `1.0.0` | Asset versioning, cache busting |
| `BPGW_FILE` | `__FILE__` | Plugin file reference |
| `BPGW_PATH` | `plugin_dir_path(__FILE__)` | Filesystem paths for require/include |
| `BPGW_URL` | `plugin_dir_url(__FILE__)` | Public URLs for assets |

---

## Autoloader

PSR-4 style autoloader without Composer. Registered via `spl_autoload_register`.

**Mapping rule:**

```
Namespace               →  File path
BPGW\Core\Plugin        →  app/Core/Plugin.php
BPGW\Gateways\Bkash     →  app/Gateways/BkashGateway.php
BPGW\Services\Bkash     →  app/Services/BkashService.php
BPGW\Controllers\Ajax   →  app/Controllers/AjaxController.php
```

**Implementation:**

```php
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'BPGW\\') !== 0) return;
    $relative = str_replace('BPGW\\', '', $class);
    $file = BPGW_PATH . 'app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});
```

---

## Core Classes

### Plugin.php

**Location:** `app/Core/Plugin.php`  
**Pattern:** Singleton  
**Responsibility:** Register all WordPress hooks. Nothing else.

**Key methods:**

| Method | Hook | Description |
|---|---|---|
| `boot()` | Called on `plugins_loaded` | Creates singleton, calls registerHooks() |
| `registerHooks()` | — | Wires all actions and filters |
| `registerAdminMenu()` | `admin_menu` | Registers BPGW sidebar item |
| `enqueueAdminAssets()` | `admin_enqueue_scripts` | Loads CSS/JS on BPGW admin page only |
| `renderAdminPage()` | Callback for admin menu | Includes admin template |

### GatewayManager.php

**Location:** `app/Core/GatewayManager.php`  
**Pattern:** Static utility  
**Responsibility:** Maintain the list of registered gateway classes.

To add a new gateway, add its class to the array in `register()`:

```php
public static function register(array $gateways): array
{
    $gateways[] = \BPGW\Gateways\BkashGateway::class;
    $gateways[] = \BPGW\Gateways\SSLCommerzGateway::class;
    // $gateways[] = \BPGW\Gateways\NagadGateway::class; ← add here
    return $gateways;
}
```

---

## Gateway Classes

Gateway classes extend `WC_Payment_Gateway`. They act as adapters between WooCommerce and our Service classes.

**Rule:** Gateway classes must NOT contain API logic. They delegate to Service classes.

### BkashGateway.php

**Location:** `app/Gateways/BkashGateway.php`  
**Extends:** `WC_Payment_Gateway`  
**WooCommerce ID:** `bpgw_bkash`

**Declared properties:**

```php
public string $app_key    = '';
public string $app_secret = '';
public string $username   = '';
public string $password   = '';
```

Required for PHP 8.2+ compatibility. Dynamic properties are deprecated.

**Settings stored in:** `wp_options` table under key `woocommerce_bpgw_bkash_settings`

**Key methods:**

| Method | Description |
|---|---|
| `__construct()` | Sets gateway properties, loads settings |
| `init_form_fields()` | Defines WooCommerce settings form fields |
| `process_payment($order_id)` | Called by WooCommerce on Place Order |

**process_payment() flow:**

```
1. Load WC order
2. Check sandbox mode
3. Instantiate BkashService with credentials
4. Build callback URL
5. Call BkashService::createPayment()
6. If URL returned → redirect customer
7. If null returned → show error, return fail
```

### SSLCommerzGateway.php

**Location:** `app/Gateways/SSLCommerzGateway.php`  
**Extends:** `WC_Payment_Gateway`  
**WooCommerce ID:** `bpgw_sslcommerz`

**Settings stored in:** `wp_options` table under key `woocommerce_bpgw_sslcommerz_settings`

Credentials: Store ID, Store Password only. No token system needed.

---

## Service Classes

Service classes contain all API logic. They are pure PHP — no WordPress, no WooCommerce dependencies except `wc_get_logger()` for logging.

**Rule:** Service classes must NOT know about WooCommerce orders. They receive plain data and return plain data.

### BkashService.php

**Location:** `app/Services/BkashService.php`

**Constructor parameters:**

```php
public function __construct(
    string $appKey,
    string $appSecret,
    string $username,
    string $password,
    bool $sandbox = false
)
```

**API Endpoints:**

| Method | Endpoint | Description |
|---|---|---|
| `getToken()` | `/tokenized/checkout/token/grant` | Get auth token |
| `createPayment()` | `/tokenized/checkout/create` | Create payment, get redirect URL |
| `verifyPayment()` | `/tokenized/checkout/execute` | Execute and verify payment |

**Base URLs:**

```
Sandbox: https://tokenized.sandbox.bka.sh/v1.2.0-beta
Live:    https://tokenized.pay.bka.sh/v1.2.0-beta
```

**Token caching:**

bKash tokens expire after 1 hour. BPGW caches the token in `wp_options` for 55 minutes.

```
wp_options key: bpgw_bkash_token
Value structure: { token: string, created_at: timestamp }
```

On each `getToken()` call:
1. Check stored token age
2. If under 55 minutes — return stored token
3. If expired or missing — request new token, store it, return it

**getToken() headers:**

```
Content-Type: application/json
Accept: application/json
username: {username}
password: {password}
```

**getToken() body:**

```json
{
    "app_key": "your_app_key",
    "app_secret": "your_app_secret"
}
```

**createPayment() headers:**

```
Content-Type: application/json
Accept: application/json
Authorization: {id_token}
X-APP-Key: {app_key}
```

**createPayment() body:**

```json
{
    "mode": "0011",
    "payerReference": "{order_id}",
    "callbackURL": "https://yoursite.com/?wc-api=bpgw_bkash_callback&order_id={order_id}",
    "amount": "100.00",
    "currency": "BDT",
    "intent": "sale",
    "merchantInvoiceNumber": "{order_id}"
}
```

**verifyPayment() headers:**

```
Content-Type: application/json
Accept: application/json
Authorization: {fresh_or_cached_token}
X-APP-Key: {app_key}
```

**verifyPayment() body:**

```json
{
    "paymentID": "TR0011xxxx"
}
```

**Success response statusCode:** `0000`

`verifyPayment()` fetches/reuses token internally during callback verification.

### SSLCommerzService.php

**Location:** `app/Services/SSLCommerzService.php`

No token system. Each request uses Store ID and Store Password directly.

**API Endpoints:**

| Method | Endpoint | Description |
|---|---|---|
| `createPayment()` | `/gwprocess/v4/api.php` | Init payment, get GatewayPageURL |
| `verifyPayment()` | `/validator/api/merchantTransIDvalidationAPI.php` | Validate via tran_id |

**Base URLs:**

```
Sandbox: https://sandbox.sslcommerz.com
Live:    https://securepay.sslcommerz.com
```

---

## Controller Classes

### SettingsController.php

**Location:** `app/Controllers/SettingsController.php`  
**Pattern:** Static  
**Responsibility:** Render the admin settings page template.

```php
public static function render(): void
{
    include BPGW_PATH . 'templates/admin-settings.php';
}
```

### AjaxController.php

**Location:** `app/Controllers/AjaxController.php`  
**Pattern:** Static  
**Responsibility:** Handle AJAX requests from the admin JS.

**Registered actions:**

| WordPress Action | Method | Description |
|---|---|---|
| `wp_ajax_bpgw_save_settings` | `saveSettings()` | Save sandbox mode to options table |

**Security:** Every AJAX handler must verify nonce before processing:

```php
if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bpgw_admin_nonce')) {
    wp_send_json_error(['message' => 'Invalid nonce.']);
    return;
}
```

### CallbackController.php

**Location:** `app/Controllers/CallbackController.php`  
**Pattern:** Static  
**Responsibility:** Handle payment callbacks from gateways.

**Registered hooks:**

| WordPress Action | Method | Trigger |
|---|---|---|
| `woocommerce_api_bpgw_bkash_callback` | `handleBkash()` | `?wc-api=bpgw_bkash_callback` |
| `woocommerce_api_bpgw_sslcommerz_callback` | `handleSSLCommerz()` | `?wc-api=bpgw_sslcommerz_callback` |

---

## Payment Flows

### bKash Hosted Payment Flow

```
1. Customer selects bKash → clicks Place Order
2. WooCommerce calls BkashGateway::process_payment($order_id)
3. BkashService::getToken() → checks cache → returns token
4. BkashService::createPayment($orderId, $amount, $callbackUrl)
   POST /tokenized/checkout/create
   Response: { bkashURL: "https://..." }
5. process_payment returns { result: success, redirect: bkashURL }
6. WooCommerce redirects customer to bkashURL
7. Customer pays on bKash hosted page
8. bKash redirects to callbackUrl with:
    ?wc-api=bpgw_bkash_callback&order_id=25&paymentID=TR001xxx&status=success&signature=...&apiVersion=1.2.0-beta/
9. WordPress fires: woocommerce_api_bpgw_bkash_callback
10. CallbackController::handleBkash() runs
11. Validates order, payment method, callback status, and paymentID
12. BkashService::verifyPayment($paymentId) requests/reuses token internally
13. BkashService calls /tokenized/checkout/execute
    POST /tokenized/checkout/execute
    Response: { statusCode: "0000" }
14. $order->payment_complete($paymentId)
15. Redirect to order received page
```

### bKash Cancellation Flow

```
Customer cancels on bKash page
        ↓
bKash redirects with status=cancel
        ↓
CallbackController detects cancel/cancelled/canceled/failure
        ↓
$order->update_status('failed', 'bKash payment cancelled or failed.')
        ↓
Redirect to cart/cancel URL
```

### SSLCommerz Hosted Payment Flow

```
1. Customer selects SSLCommerz → clicks Place Order
2. WooCommerce calls SSLCommerzGateway::process_payment($order_id)
3. SSLCommerzService::createPayment() 
   POST to /gwprocess/v4/api.php with full customer + order data
   Response: { GatewayPageURL: "https://..." }
4. Redirect customer to GatewayPageURL
5. Customer pays on SSLCommerz hosted page
6. SSLCommerz POSTs to success_url/fail_url/cancel_url
7. CallbackController::handleSSLCommerz() runs
8. Validates order, payment method, already-paid state, callback status, and tran_id
9. For fail/cancel statuses, marks order failed and exits
10. SSLCommerzService::verifyPayment($tran_id)
   GET /validator/api/merchantTransIDvalidationAPI.php?tran_id=xxx
   Response: { status: "VALID" }
11. $order->payment_complete($tran_id)
12. Redirect to order received page
```

### SSLCommerz Callback Notes

Current implementation verifies using `tran_id` from callback query and validates via merchant transaction validation API. It also protects against payment-method mismatch and duplicate callback processing for already-paid orders.

---

## Database Storage

BPGW uses the WordPress Options API exclusively. No custom tables.

### Options Table Entries

| Option Key | Type | Description |
|---|---|---|
| `bpgw_sandbox_mode` | bool | Global sandbox toggle |
| `bpgw_bkash_token` | array | Cached bKash auth token |
| `woocommerce_bpgw_bkash_settings` | array | bKash gateway settings |
| `woocommerce_bpgw_sslcommerz_settings` | array | SSLCommerz gateway settings |

### bpgw_bkash_token Structure

```php
[
    'token'      => 'eyJhbGciOiJSUzI1NiIs...', // JWT token string
    'created_at' => 1741234567,                  // Unix timestamp
]
```

Token is invalidated and refreshed when age exceeds 55 minutes (3300 seconds).

### woocommerce_bpgw_bkash_settings Structure

```php
[
    'enabled'    => 'yes',
    'title'      => 'bKash',
    'app_key'    => 'encrypted_value',
    'app_secret' => 'encrypted_value',
    'username'   => 'encrypted_value',
    'password'   => 'encrypted_value',
]
```

### woocommerce_bpgw_sslcommerz_settings Structure

```php
[
    'enabled'        => 'yes',
    'title'          => 'SSLCommerz',
    'store_id'       => 'encrypted_value',
    'store_password' => 'encrypted_value',
]
```

---

## AJAX System

WordPress AJAX routes all requests through `admin-ajax.php` using an `action` parameter.

**Pattern:**

```
JS fetch() → POST admin-ajax.php?action=bpgw_save_settings
                    ↓
WordPress fires: wp_ajax_bpgw_save_settings
                    ↓
AjaxController::saveSettings() runs
                    ↓
wp_send_json_success() or wp_send_json_error()
                    ↓
JS receives JSON response
```

**PHP data passed to JS via wp_localize_script:**

```js
window.BPGW = {
    ajaxUrl: "https://yoursite.com/wp-admin/admin-ajax.php",
    nonce:   "a1b2c3d4e5"
}
```

**JS fetch pattern:**

```js
fetch(BPGW.ajaxUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'bpgw_save_settings',
        nonce:  BPGW.nonce,
        // ... other data
    }),
})
.then(res => res.json())
.then(data => {
    if (data.success) { /* handle success */ }
});
```

---

## Asset Pipeline

### Build System

- **Tool:** Tailwind CSS v4 CLI
- **Input:** `assets/tailwind.css`
- **Output:** `assets/css/style.css`
- **Config:** `tailwind.config.js`

### Tailwind Configuration

```js
export default {
    content: [
        './templates/**/*.php',
        './app/**/*.php',
        './assets/js/**/*.js',
    ],
}
```

### CSS Prefix

All Tailwind utility classes use the `bpgw:` prefix to avoid conflicts with WordPress and WooCommerce styles:

```html
<!-- Correct -->
<div class="bpgw:flex bpgw:gap-6">

<!-- Wrong — will conflict with other styles -->
<div class="flex gap-6">
```

Preflight (CSS reset) is disabled. BPGW only styles its own elements.

### Build Commands

```bash
npm run dev    # Watch mode — recompiles on file change
npm run build  # Production — minified output
```

---

## Block Checkout Support

WooCommerce's block-based checkout requires separate registration from the classic shortcode checkout.

### PHP Side

Each gateway has a corresponding block support class extending `AbstractPaymentMethodType`:

```
app/Gateways/Blocks/BkashBlockSupport.php
app/Gateways/Blocks/SSLCommerzBlockSupport.php
```

Registered in `Plugin.php`:

```php
add_action('woocommerce_blocks_payment_method_type_registration', function($registry) {
    $registry->register(new \BPGW\Gateways\Blocks\BkashBlockSupport());
    $registry->register(new \BPGW\Gateways\Blocks\SSLCommerzBlockSupport());
});
```

### JS Side

Each gateway has a block registration script:

```
assets/js/bkash-block.js
assets/js/sslcommerz-block.js
```

Uses WooCommerce's `registerPaymentMethod()` from `wc.wcBlocksRegistry`.

Each script is wrapped in an isolated scope to avoid top-level variable collisions when both gateways are enabled.

---

## Adding a New Gateway

Follow these steps to add a new gateway (e.g. Nagad):

### Step 1 — Create the Service

```
app/Services/NagadService.php
```

Implement:
- `__construct(credentials, sandbox)`
- `createPayment($orderId, $amount, $callbackUrl): ?string`
- `verifyPayment($paymentId): bool`

### Step 2 — Create the Gateway

```
app/Gateways/NagadGateway.php
```

- Extend `WC_Payment_Gateway`
- Set `$this->id = 'bpgw_nagad'`
- Define credentials in `init_form_fields()`
- Delegate to `NagadService` in `process_payment()`

### Step 3 — Create Block Support

```
app/Gateways/Blocks/NagadBlockSupport.php
assets/js/nagad-block.js
```

### Step 4 — Register in GatewayManager

```php
$gateways[] = \BPGW\Gateways\NagadGateway::class;
```

### Step 5 — Register Callback Hook

In `Plugin.php`:

```php
add_action(
    'woocommerce_api_bpgw_nagad_callback',
    [\BPGW\Controllers\CallbackController::class, 'handleNagad']
);
```

### Step 6 — Add Callback Handler

In `CallbackController.php`:

```php
public static function handleNagad(): void
{
    // Similar to handleBkash()
}
```

---

## Security

### Nonce Validation

Every AJAX request is protected by WordPress nonces:

```php
// Generated in PHP (per page load)
wp_create_nonce('bpgw_admin_nonce')

// Verified in AJAX handler
wp_verify_nonce($_POST['nonce'] ?? '', 'bpgw_admin_nonce')
```

### Input Sanitization

All `$_GET` and `$_POST` values are sanitized before use:

```php
$order_id  = absint($_GET['order_id']);           // Integer only
$paymentId = sanitize_text_field($_GET['paymentID']); // Strip HTML/JS
$status    = sanitize_text_field($_GET['status']);
```

### Direct File Access Prevention

Every PHP file starts with:

```php
defined('ABSPATH') || exit;
```

`ABSPATH` is only defined when WordPress is running. Direct browser access returns nothing.

### Credential Storage

Gateway credentials are stored in `wp_options` via WooCommerce's built-in settings system. They are never exposed in source code or logs.

### Payment Verification

**Never trust the redirect alone.** Always verify payment server-to-server:

```
bKash:      POST /tokenized/checkout/execute  → statusCode === '0000'
SSLCommerz: GET  /validator/api/...           → status === 'VALID'
```

A malicious user could craft a fake callback URL with `status=success`. Server-side verification prevents this.

---

## Local Development

### Requirements

- LocalWP or similar local WordPress environment
- Node.js 18+
- PHP 8.0+

### Setup

```bash
# Clone into plugins directory
cd wp-content/plugins/
git clone https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce.git bangladesh-payment-gateways-woocommerce

# Install JS dependencies
cd bangladesh-payment-gateways-woocommerce
npm install

# Start Tailwind watch
npm run dev
```

### Testing Payments Locally

bKash and SSLCommerz require publicly accessible callback URLs. Use ngrok:

```bash
ngrok http 80
```

Use the ngrok URL as your callback URL during local testing.

### Logging

BPGW uses WooCommerce's logger with source `bpgw-bkash` and `bpgw-sslcommerz`:

```php
$logger = wc_get_logger();
$logger->debug('Message here', ['source' => 'bpgw-bkash']);
```

View logs at: **WooCommerce → Status → Logs**

---

## Code Style

- PHP 8.0+ syntax
- Strict types where possible
- PSR-4 namespacing
- No Composer, no vendor directory
- No jQuery — vanilla JS only
- WordPress coding standards for hooks and filters
- All public-facing strings wrapped in `__()` or `esc_html__()` for i18n