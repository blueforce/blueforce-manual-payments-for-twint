=== Blueforce Manual Payments for TWINT ===
Contributors: worshipper
Tags: woocommerce, twint, payment gateway, switzerland, manual payment
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manual TWINT payment method for WooCommerce – no API and no contract with TWINT required. Incoming payments are confirmed by hand.

== Description ==

This plugin adds a TWINT payment method to WooCommerce that works without the TWINT API, without an acquiring contract and without a payment service provider. It uses the manual TWINT process (send or request money by mobile number) and is therefore suited to small shops, clubs and sole traders.

TWINT does not offer its payment API publicly. An automated integration is only possible through a TWINT acquiring contract or a payment service provider. This plugin deliberately takes the manual route, which everyone can use right away.

= Two workflows =

* **Customer sends:** The customer is shown your TWINT mobile number and, optionally, your QR code. They send the amount using the order number as the message.
* **I request:** The customer enters their TWINT mobile number; you request the amount in the TWINT app.

In both cases the order is set to "On hold" and the incoming payment is confirmed by hand.

= Features =

* Classic and block checkout
* Optional TWINT QR code on the thank-you page and in the email
* HPOS compatible
* Fully translatable (de, en, fr_CH, it_CH)
* No external dependencies, no tracking, no phone-home calls

== Installation ==

1. Upload and activate the plugin.
2. Open WooCommerce → Settings → Payments → TWINT.
3. Enable it, choose a workflow and configure it.

== Frequently Asked Questions ==

= Do I need a contract with TWINT? =

No. This plugin uses the manual TWINT process and requires neither an acquiring contract nor a payment service provider.

= Is the payment verified automatically? =

No. The incoming payment is checked in the TWINT app and the order is set to "Processing" by hand.

= Is this plugin official TWINT software? =

No. It is an independent community project by Blueforce Digital Solutions and is not affiliated with TWINT AG. "TWINT" is a registered trademark of TWINT AG and is used here only to describe compatibility.

= What personal data is stored? =

Only in the "I request" workflow: the TWINT mobile number the customer enters at checkout (as order metadata, used solely to request the payment). It is included in the WordPress data export and erasure tools; a suggested privacy policy snippet is available under Settings → Privacy. In the "Customer sends" workflow, no personal payment data is collected.

== Privacy ==

In the "I request" workflow the plugin stores the TWINT mobile number provided by the customer as order metadata (`_bf_twint_customer_phone`) in order to request the payment via the TWINT app. This number is included in the WooCommerce/WordPress data export and erasure tools. No data is sent to third parties and no external services are contacted; payment reconciliation is done manually in the TWINT app.

== Changelog ==

= 1.4.0 =
* Published in the WordPress plugin directory; plugin renamed to "Blueforce Manual Payments for TWINT".
* Updates now run directly through WordPress.org; the previous GitHub update mechanism has been removed (no more external calls).
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
* TWINT logo as the plugin icon in the plugin list.
* English translations (en_GB/en_US) added for the new admin texts (QR image selection).

= 1.1.0 =
* TWINT QR image: select directly from the media library via a button (instead of typing a URL), with preview.

= 1.0.2 =
* Block checkout: TWINT logo next to the method name and required-field marker ("*") on the mobile number.

= 1.0.1 =
* Internal improvements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.0 =
Published in the WordPress plugin directory. Updates now run through WordPress.org; no more external calls. No functional changes.
