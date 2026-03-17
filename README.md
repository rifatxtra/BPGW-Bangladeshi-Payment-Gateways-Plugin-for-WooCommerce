# BPGW — Bangladeshi Payment Gateways Plugin for WooCommerce

Accept payments from Bangladeshi customers directly in your WooCommerce store.  
Supports **bKash** and **SSLCommerz** with a clean hosted payment flow.

**Author:** [Md. Rashedul Islam](https://rifatxtra.com)  
**Plugin Site:** [bpgw.rifatxtra.com](https://bpgw.rifatxtra.com)  
**GitHub:** [BPGW on GitHub](https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce)  
**License:** GPL v2 or later

---

## Supported Gateways

| Gateway | Status | Type |
|---|---|---|
| bKash | ✅ Live | Hosted Payment Flow |
| SSLCommerz | ✅ Live | Hosted Payment Flow |
| Manual Payment | ✅ Live | Personal Account Payment |
| Nagad | 🔜 Coming Soon | — |
| Rocket | 🔜 Coming Soon | — |
| Upay | 🔜 Coming Soon | — |

---

## Requirements

- PHP 8.0 or higher
- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- SSL certificate (required by bKash and SSLCommerz for live mode)

---

## Installation

### Option 1 — Upload via WordPress Admin

1. Download the latest release ZIP from [GitHub Releases](https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Option 2 — Manual Upload via FTP

1. Download and extract the ZIP file
2. Upload the `bangladesh-payment-gateways-woocommerce` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins**
4. Find **BPGW** and click **Activate**

---

## Configuration

### Step 1 — Global Settings

Go to **WordPress Admin → BPGW**

| Setting | Description |
|---|---|
| Sandbox Mode | Enable this during development/testing. Disables for all gateways at once. |

---

### Step 2 — bKash Setup

Go to **WooCommerce → Settings → Payments → bKash → Manage**

| Field | Description |
|---|---|
| Enable/Disable | Turn bKash on or off at checkout |
| Title | Label shown to customers at checkout |
| App Key | From your bKash merchant account |
| App Secret | From your bKash merchant account |
| Username | From your bKash merchant account |
| Password | From your bKash merchant account |

#### Getting bKash Credentials

1. Apply for a bKash merchant account at [pgw.bkash.com](https://pgw.bkash.com)
2. Once approved, log in to your merchant portal
3. Navigate to **API Credentials**
4. Copy your App Key, App Secret, Username, and Password
5. For sandbox testing, use credentials from [developer.bka.sh](https://developer.bka.sh)

#### bKash Callback URL

Register this URL in your bKash merchant portal:

```
https://yoursite.com/?wc-api=bpgw_bkash_callback
```

Replace `yoursite.com` with your actual domain.

After payment, bKash returns to this URL with query parameters like:

```
?wc-api=bpgw_bkash_callback&order_id=16&paymentID=TR0011...&status=success&signature=...&apiVersion=1.2.0-beta/
```

The plugin does **not** trust this redirect alone — it verifies payment server-to-server before marking the order paid.

---

### Step 3 — SSLCommerz Setup

Go to **WooCommerce → Settings → Payments → SSLCommerz → Manage**

| Field | Description |
|---|---|
| Enable/Disable | Turn SSLCommerz on or off at checkout |
| Title | Label shown to customers at checkout |
| Store ID | From your SSLCommerz merchant account |
| Store Password | From your SSLCommerz merchant account |

#### Getting SSLCommerz Credentials

1. Register at [sslcommerz.com](https://sslcommerz.com)
2. Once approved, log in to your merchant panel
3. Go to **Store Settings**
4. Copy your Store ID and Store Password
5. For sandbox, register at [sandbox.sslcommerz.com](https://sandbox.sslcommerz.com)

#### SSLCommerz Callback URLs

Register these in your SSLCommerz merchant panel:

```
Success URL: https://yoursite.com/?wc-api=bpgw_sslcommerz_callback
Fail URL:    https://yoursite.com/?wc-api=bpgw_sslcommerz_callback
Cancel URL:  https://yoursite.com/?wc-api=bpgw_sslcommerz_callback
IPN URL:     https://yoursite.com/?wc-api=bpgw_sslcommerz_ipn
```

---

## Sandbox / Test Mode

Enable **Sandbox Mode** in **WordPress Admin → BPGW** to switch all gateways to test mode.

### bKash Sandbox Test Credentials

Get sandbox credentials from [developer.bka.sh](https://developer.bka.sh)

### SSLCommerz Sandbox

Use your sandbox Store ID and Password from [sandbox.sslcommerz.com](https://sandbox.sslcommerz.com)

---

## Payment Flow

```
Customer selects gateway at checkout
          ↓
Clicks Place Order
          ↓
Plugin creates payment request via API
          ↓
Customer redirected to gateway hosted page
          ↓
Customer completes payment
          ↓
Gateway redirects back to your store
          ↓
Plugin verifies payment via API
          ↓
WooCommerce order marked as paid
          ↓
Customer sees Order Received page
```

---

## Frequently Asked Questions

**Does this plugin store card numbers or sensitive payment data?**  
No. All payments happen on the gateway's hosted page. No payment data touches your server.

**Does this work with WooCommerce Blocks checkout?**  
Yes. Both classic shortcode checkout and the new block-based checkout are supported.

**Can I use both gateways at the same time?**  
Yes. Enable both and customers can choose at checkout.

If both are enabled, both methods are shown in block checkout as separate options.

**What currency does this support?**  
BDT (Bangladeshi Taka) only.

**Is this plugin free?**  
Yes. Free forever. GPL licensed. No premium version.

---

## Documentation Site

A PHP documentation website and landing site are included under `site/`.

- Docs app entry: `site/docs/index.php`
- Local run instructions: `site/docs/README.md`
- Landing site entry: `site/index.php`

---

## Support

- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce/issues)
- 💬 **Discussion:** [GitHub Discussions](https://github.com/rifatxtra/BPGW-Bangladeshi-Payment-Gateways-for-WooCommerce/discussions)
- 🌐 **Website:** [bpgw.rifatxtra.com](https://bpgw.rifatxtra.com)

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a pull request.

Areas where help is needed:
- Nagad gateway integration
- Rocket gateway integration
- Upay gateway integration
- Unit tests
- Documentation improvements

---

## Changelog

### 1.0.1
- Fixed WooCommerce Blocks registration conflict so bKash and SSLCommerz can appear together when both are enabled
- Improved bKash callback handling for subdomain/public return URLs
- Added stronger callback validation and logging for `order_id`, `paymentID`, `status`, `signature`, and `apiVersion`
- Updated bKash verification flow to fetch token safely during callback verification

### 1.0.0
- Initial release
- bKash hosted payment flow
- SSLCommerz hosted payment flow
- Sandbox mode
- WooCommerce Blocks support
- PHP 8.0+ compatibility