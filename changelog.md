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
