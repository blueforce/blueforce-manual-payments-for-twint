=== Blueforce Manual Payments for TWINT ===
Contributors: worshipper
Tags: woocommerce, twint, payment gateway, switzerland, manual payment
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manual TWINT payment method for WooCommerce – the plugin needs no TWINT API or acquiring contract. Payments are confirmed by hand.

== Description ==

This plugin adds a TWINT payment method to WooCommerce. The **plugin itself** needs no TWINT API, no acquiring contract and no payment service provider – it uses the manual TWINT process (send or request money by mobile number) and is therefore suited to small shops, clubs and sole traders. You remain responsible for complying with your own TWINT, bank and business terms for commercial use.

TWINT does not offer its payment API publicly. An automated integration is only possible through a TWINT acquiring contract or a payment service provider. This plugin deliberately takes the manual route, so you can start without a TWINT integration contract while still meeting your own TWINT and bank conditions.

= Two workflows =

* **Customer sends:** The customer is shown your TWINT mobile number and, optionally, your QR code. They send the amount using the order number as the message.
* **I request:** The customer enters their TWINT mobile number; you request the amount in the TWINT app.

In both cases the order is set to "On hold" and the incoming payment is confirmed by hand.

= Features =

* Classic and block checkout
* Optional TWINT QR code on the thank-you page and in the email
* HPOS compatible
* Translation-ready; German, English, French (CH) and Italian (CH) translations included
* No external dependencies, no tracking, no phone-home calls

== Installation ==

1. Upload and activate the plugin.
2. Open WooCommerce → Settings → Payments → TWINT.
3. Enable it, choose a workflow and configure it.

== Frequently Asked Questions ==

= Do I need a contract with TWINT? =

The plugin itself needs no TWINT API key, acquiring contract or payment service provider – it uses the manual TWINT process. Please note that the terms of your TWINT, bank and merchant account still apply: if you accept payments commercially, check your own TWINT and bank conditions for business use.

= Is the payment verified automatically? =

No. The incoming payment is checked in the TWINT app and the order is set to "Processing" by hand.

= Is this plugin official TWINT software? =

No. It is an independent community project by Blueforce Digital Solutions and is not affiliated with TWINT AG. "TWINT" is a registered trademark of TWINT AG and is used here only to describe compatibility.

= What personal data is stored? =

Only in the "I request" workflow: the TWINT mobile number the customer enters at checkout (as order metadata, used solely to request the payment). It is included in the WordPress data export and erasure tools; a suggested privacy policy snippet is available under Settings → Privacy. In the "Customer sends" workflow, no personal payment data is collected.

== Privacy ==

In the "I request" workflow the plugin stores the TWINT mobile number provided by the customer as order metadata (`_bf_twint_customer_phone`) in order to request the payment via the TWINT app. This number is included in the WooCommerce/WordPress data export and erasure tools. No data is sent to third parties and no external services are contacted; payment reconciliation is done manually in the TWINT app.

== Changelog ==

= 1.4.3 =
* Wording: clarified that the plugin itself needs no TWINT API key or acquiring contract, while shop operators remain responsible for their own TWINT, bank and merchant terms for commercial use.
* Removed "TWINT logo" phrasing from older changelog notes to avoid trademark ambiguity; the plugin icon is a custom Blueforce design.
* Updated the translation template and metadata to the current version.
* Hardening: the selected workflow (send/request) is normalised to a known value on load and save; block checkout data is sanitised before it is passed to the front end.
* Packaging/CI: the build script now verifies that development, test, repo and WordPress.org asset folders never end up in the distributed plugin ZIP; PHP lint is limited to the actual plugin files.
* Cleanup: added uninstall.php so deleting the plugin removes its stored gateway settings (order data is kept).
* Consistency: aligned the license notation between the plugin header and readme, and updated remaining internal doc comments to the current plugin name.

= 1.4.2 =
* Coding standards: renamed the gateway class to use the plugin prefix (BF_TWINT_Gateway).
* Header: shortened the plugin description to under 140 characters, added "Requires Plugins: woocommerce", and updated "WC tested up to".
* Packaging: include composer.json in the distributed plugin; keep GitHub-only docs (README.md, CHANGELOG.md) out of the ZIP.
* No functional changes.

= 1.4.1 =
* Security/hardening: escape settings field output late with wp_kses_post() (tooltip and description HTML in the QR image field); removed the corresponding phpcs:ignore annotations. No functional changes.

= 1.4.0 =
* Renamed to "Blueforce Manual Payments for TWINT" and prepared for the WordPress.org plugin directory.
* Removed the previous GitHub-based update mechanism; the plugin no longer makes external calls.
* No functional changes to checkout, workflows or privacy.

= 1.3.0 =
* Order snapshot: workflow, number, account holder, QR image and notes are frozen per order – thank-you page, email and admin stay correct even if the settings are changed later.
* Block checkout: TWINT is now correctly hidden for foreign currencies (as in the classic checkout).
* Privacy: customer number is included in data export/erasure; privacy policy snippet added.
* Admin notice for incomplete configuration; real plain-text email; centralised phone validation/normalisation.
* Accessibility improvements; inline styles moved to CSS; "Mark as paid" button restricted to authorised roles, with a logged note.
* CI: PHP lint, WordPress Coding Standards and ZIP build test.

= 1.2.0 =
* "Mark as paid" button in the order screen: release a TWINT order as paid with one click.
* French (fr_CH) and Italian (it_CH) translations – including block checkout.
* Copy button for the order number on the thank-you page (fewer typos in the TWINT message).
* TWINT is only shown when the shop currency is CHF (filter "bf_twint_is_available" to override).

= 1.1.2 =
* Security: additional capability check (manage_woocommerce) when loading the admin scripts.

= 1.1.1 =
* Plugin icon shown in the plugin list.
* English translations (en_GB/en_US) added for the new admin texts (QR image selection).

= 1.1.0 =
* TWINT QR image: select directly from the media library via a button (instead of typing a URL), with preview.

= 1.0.2 =
* Block checkout: payment method icon next to the method name and required-field marker ("*") on the mobile number.

= 1.0.1 =
* Internal improvements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.0 =
Renamed and prepared for the WordPress.org plugin directory; the GitHub-based update mechanism was removed (no more external calls). No functional changes.
