=== TWINT for WooCommerce ===
Contributors: blueforce
Tags: woocommerce, twint, payment, payment gateway, switzerland
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.2
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

= Welche personenbezogenen Daten werden gespeichert? =

Nur im Ablauf «Ich fordere an»: die vom Kunden im Checkout angegebene TWINT-Handynummer (als Bestell-Metadatum, ausschliesslich zur Zahlungsanforderung). Sie wird in den WordPress-Datenexport und in die Datenlöschung einbezogen; ein Textbaustein für die Datenschutzerklärung steht unter Einstellungen → Datenschutz bereit. Im Ablauf «Kunde sendet» werden keine personenbezogenen Zahlungsdaten erfasst.

== Privacy ==

Im Ablauf «Ich fordere an» speichert das Plugin die vom Kunden angegebene TWINT-Handynummer als Bestell-Metadatum (`_bf_twint_customer_phone`), um die Zahlung über die TWINT-App anzufordern. Diese Nummer wird vom WooCommerce-/WordPress-Datenexport und der Datenlöschung berücksichtigt. Es werden keine Daten an Dritte übermittelt; der Zahlungsabgleich erfolgt manuell in der TWINT-App. Hinweis: Für Plugin-Updates wird die GitHub-Releases-API dieses Repositorys kontaktiert (siehe Abschnitt «Automatische Updates»).

== Changelog ==

= 1.3.2 =
* Migrations-Schutz: Sind versehentlich die alte und die neue Version gleichzeitig aktiv, lädt sich diese alte Version nicht mehr (verhindert einen «Cannot redeclare»-Fatal) und zeigt nur einen Hinweis, dass sie gelöscht werden kann. Der Umzugshinweis empfiehlt jetzt, dieses Plugin vor dem Aktivieren der neuen Version zu deaktivieren.

= 1.3.1 =
* Umzug: Dieses Plugin ist ins offizielle WordPress-Plugin-Verzeichnis umgezogen und heisst dort «Blueforce Manual Payments for TWINT». Diese GitHub-Version wird nicht mehr weiterentwickelt. Ein Hinweis im Backend verlinkt die neue Version; Einstellungen und Bestellungen bleiben beim Wechsel erhalten (gleiche Gateway-ID).

= 1.3.0 =
* Order-Snapshot: Ablauf, Nummer, Kontoinhaber, QR-Bild und Hinweise werden pro Bestellung eingefroren – Danke-Seite, E-Mail und Backend bleiben korrekt, auch wenn die Einstellungen später geändert werden.
* Block-Checkout: TWINT wird bei Fremdwährung jetzt korrekt ausgeblendet (wie im klassischen Checkout).
* Datenschutz: Kundennummer wird in Datenexport/-löschung einbezogen; Textbaustein für die Datenschutzerklärung ergänzt.
* Admin-Hinweis bei unvollständiger Konfiguration; echte Plain-Text-E-Mail; zentrale Telefon-Validierung/-Normalisierung.
* Accessibility verbessert; Inline-Styles in CSS ausgelagert; «Zahlung erhalten»-Button nur für berechtigte Rollen, mit Protokoll.
* CI: PHP-Lint, WordPress Coding Standards und ZIP-Build-Test.

= 1.2.0 =
* «Zahlung erhalten»-Button in der Bestellansicht: TWINT-Bestellung per Klick als bezahlt freigeben.
* Französische (fr_CH) und italienische (it_CH) Übersetzung – inkl. Block-Checkout.
* Kopier-Button für die Bestellnummer auf der Danke-Seite (weniger Tippfehler bei der TWINT-Mitteilung).
* TWINT wird nur noch bei Shop-Währung CHF angezeigt (Filter «bf_twint_is_available» zum Übersteuern).

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
