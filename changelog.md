#### 2.0.4
- Markdown Formatting: Converted `readme.md` to pure GitHub Flavored Markdown (removed raw HTML elements).
- Release Automation & Tooling: Fully synchronized automated GitHub Release action workflow and version bump scripts.

#### 2.0.3
- Enterprise & Fail-Safe Hardening: Added explicit error logging across all API calls, webhook handlers, AJAX routes, and 3D endpoints so no errors or exceptions are swallowed silently.
- Expanded Unit & Integration Test Suite: Added comprehensive unit test suites for `Client`, `Installments`, and `Reconciler`, plus expanded integration tests for WooCommerce refunds and failed payment transitions.
- GitHub Release Automation: Added `.github/workflows/release.yml` GitHub Action to automatically build and attach clean distribution `.zip` packages on tag releases.
- Tooling: Added cross-platform version bump scripts (`bin/set-version.sh`, `bin/set-version.ps1`, `composer set-version`).

#### 2.0.2
- WordPress Plugin Check compliance: "Tested up to" updated to the current WordPress release, and the invoice-lookup query is now fully covered by the phpcs prepared-SQL exemption.

#### 2.0.1
- Renamed the plugin slug and text domain to `qnbpay-for-woocommerce` (WordPress.org naming compliance).
- WordPress Plugin Check fixes: i18n translators comments, text-domain consistency, `$_GET`/`$_SERVER` sanitization, output escaping.
- Removed unused card-brand images and dead CSS; fixed the BIN-lookup request race (stale AJAX requests are aborted + sequence-guarded).
- Version is now derived from the plugin header (single source of truth) with a CI consistency guard.

#### 2.0.0
- Full rewrite: PSR-4 namespaced architecture with a dependency-free autoloader (removed rappasoft/laravel-helpers and its global `data_get()`).
- HPOS (custom order tables) compatibility declared and used throughout.
- WooCommerce Cart/Checkout Blocks payment method integration.
- PCI improvement: card data (PAN/CVV) is no longer stored in order/post meta; the 3D form lives only in a short-lived, single-use transient.
- Added refund support via the QNBPay refund API.
- Secure logging via the WooCommerce logger (protected log directory) with masking.
- Guarded loading so the plugin never fatals when WooCommerce is inactive.
- Full try/catch coverage on API/payment flows; numerous bug fixes.
- PHP 7.4 - 8.4 compatibility hardening.
- Transaction history now uses the WordPress core WP_List_Table; client IP now uses WC_Geolocation.
- Added a background reconciliation job via Action Scheduler (every 10 min) that completes pending orders confirmed by checkstatus when a webhook/return is missed.
- Debug tools now link to the native WooCommerce Status > Logs page.

#### 1.0.0
- First release
