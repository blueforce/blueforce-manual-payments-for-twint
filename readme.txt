=== TWINT for WooCommerce ===
Contributors: blueforce
Tags: woocommerce, twint, payment, payment gateway, switzerland
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TWINT als Bezahlmethode für WooCommerce – ohne API, ohne Vertrag mit TWINT. Der Zahlungseingang wird von Hand bestätigt.

== Description ==

Dieses Plugin fügt WooCommerce eine TWINT-Bezahlmethode hinzu, die ohne TWINT-API, ohne Acquiring-Vertrag und ohne Payment Service Provider funktioniert. Es nutzt das manuelle TWINT-Verfahren (Geld senden / anfordern per Handynummer) und eignet sich damit für kleine Shops, Vereine und Einzelunternehmen.

TWINT stellt seine Zahlungs-API nicht öffentlich zur Verfügung. Eine automatische Integration ist nur über einen TWINT-Acquiring-Vertrag oder einen Payment Service Provider möglich. Dieses Plugin geht bewusst den manuellen Weg, der für alle sofort nutzbar ist.

= Zwei Abläufe =

* **Kunde sendet:** Dem Kunden werden deine TWINT-Handynummer und optional dein QR-Code angezeigt. Er sendet den Betrag mit der Bestellnummer als Mitteilung.
* **Ich fordere an:** Der Kunde gibt seine TWINT-Handynummer an; du forderst den Betrag in der TWINT-App an.

In beiden Fällen wird die Bestellung auf «In Wartestellung» gesetzt und der Zahlungseingang von Hand bestätigt.

= Features =

* Klassischer und Block-Checkout
* Optionaler TWINT-QR-Code auf Danke-Seite und in der E-Mail
* HPOS-kompatibel
* Vollständig übersetzbar
* Keine externen Abhängigkeiten, kein Tracking

== Installation ==

1. Plugin hochladen und aktivieren.
2. WooCommerce → Einstellungen → Zahlungen → TWINT öffnen.
3. Aktivieren, Ablauf wählen und konfigurieren.

== Frequently Asked Questions ==

= Brauche ich einen Vertrag mit TWINT? =

Nein. Dieses Plugin nutzt das manuelle TWINT-Verfahren und benötigt weder einen Acquiring-Vertrag noch einen Payment Service Provider.

= Wird die Zahlung automatisch geprüft? =

Nein. Der Zahlungseingang wird in der TWINT-App geprüft und die Bestellung von Hand auf «In Bearbeitung» gesetzt.

= Ist das Plugin offiziell von TWINT? =

Nein. Es ist ein unabhängiges Community-Projekt von Blueforce Digital Solutions und steht in keiner Verbindung zur TWINT AG.

== Changelog ==

= 1.1.2 =
* Sicherheit: zusätzlicher Berechtigungs-Check (manage_woocommerce) beim Laden der Admin-Skripte.

= 1.1.1 =
* TWINT-Logo als Plugin-Icon in der Update- und Plugin-Ansicht.
* Englische Übersetzungen (en_GB/en_US) für die neuen Admin-Texte (QR-Bild-Auswahl) ergänzt.

= 1.1.0 =
* TWINT-QR-Bild: Auswahl direkt aus der Mediathek per Button (statt URL eintippen), mit Vorschau.

= 1.0.2 =
* Block-Checkout: TWINT-Logo neben dem Methodennamen und Pflichtfeld-Markierung («*») bei der Handynummer.

= 1.0.1 =
* Automatische Updates direkt im WordPress-Backend (1-Klick) über die GitHub-Releases.

= 1.0.0 =
* Erste Veröffentlichung.
