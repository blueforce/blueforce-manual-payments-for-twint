# Blueforce Manual Payments for TWINT

> [!CAUTION]
> **Dieses Plugin ist auf WordPress.org umgezogen.**
> Installiere es ab sofort direkt aus dem offiziellen Plugin-Verzeichnis:
> **→ [wordpress.org/plugins/blueforce-manual-payments-for-twint](https://wordpress.org/plugins/blueforce-manual-payments-for-twint/)**
>
> Für **neue Installationen** bitte ausschliesslich die Version aus dem WordPress-Verzeichnis verwenden – **nicht** dieses Repository. Dieses GitHub-Repo wird **nicht weiter gepflegt** und dient nur noch als **Archiv**.

[![Version](https://img.shields.io/github/v/release/blueforce/blueforce-manual-payments-for-twint?label=Version)](https://github.com/blueforce/blueforce-manual-payments-for-twint/releases)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588a.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)

Ein kostenloses, natives WooCommerce-Bezahl-Gateway für **TWINT** – **ohne TWINT-API, ohne Acquiring-Vertrag und ohne Payment Service Provider**. Entwickelt und bereitgestellt von [Blueforce Digital Solutions](https://blueforce.ch).

> ℹ️ Das Plugin selbst braucht keinen TWINT-API-Key und keinen Acquiring-Vertrag. Die Bedingungen deines TWINT-, Bank- und Händlerkontos gelten weiterhin – wer geschäftlich Zahlungen annimmt, prüft die eigenen TWINT-/Bank-Konditionen für die gewerbliche Nutzung selbst.

> ⚠️ **Wichtig zur Einordnung:** TWINT stellt seine Zahlungs-API **nicht öffentlich** zur Verfügung. Eine echte, automatische TWINT-Integration ist nur über einen TWINT-Acquiring-Vertrag (mit Zertifikat) oder über einen Payment Service Provider (Datatrans, Saferpay, Payrexx, Worldline …) möglich und muss von TWINT zertifiziert werden.
>
> Dieses Plugin geht bewusst einen anderen Weg: Es nutzt das **manuelle TWINT-Verfahren** (Geld senden / anfordern per Handynummer). Damit funktioniert es **für alle sofort** – ideal für kleine Shops, Vereine und Einzelunternehmen, die TWINT ohne laufende Gebühren anbieten wollen.
>
> 👉 **Ausführliche Begründung:** [Warum dieses Plugin so gebaut ist](docs/WARUM-DIESES-PLUGIN.md) – warum es keine volle Integration ist und welche Designentscheidungen daraus folgen.

## Funktionsweise

Das Plugin fügt im Checkout eine TWINT-Bezahlmethode hinzu. Du wählst einen von zwei Abläufen:

### Ablauf «Kunde sendet»
- Dem Kunden werden deine **TWINT-Handynummer** und – optional – dein **TWINT-QR-Code** angezeigt.
- Der Kunde sendet den Betrag mit der **Bestellnummer als Mitteilung**.
- Die Bestellung steht auf **«In Wartestellung»**.
- Du prüfst den Zahlungseingang in der TWINT-App und setzt die Bestellung von Hand auf **«In Bearbeitung»**.

### Ablauf «Ich fordere an»
- Der Kunde gibt im Checkout seine **TWINT-Handynummer** an (Pflichtfeld).
- Die Nummer erscheint in der Bestellung im Backend.
- Du **forderst den Betrag** in deiner TWINT-App von dieser Nummer an.
- Nach Zahlungseingang setzt du die Bestellung auf **«In Bearbeitung»**.

In beiden Fällen findet **kein automatischer API-Call** statt – der Zahlungseingang wird von Hand bestätigt.

## Features

- ✅ Zwei Abläufe (Kunde sendet / Shop fordert an), pro Shop wählbar
- ✅ Klassischer **und** Block-Checkout (Cart/Checkout-Blocks, Store-API)
- ✅ Optionaler TWINT-QR-Code auf Danke-Seite und in der E-Mail
- ✅ Anweisungen auf der Danke-Seite, in der Bestell-E-Mail und im Backend
- ✅ Vollständig übersetzbar (Text-Domain `blueforce-manual-payments-for-twint`; de, en, fr_CH, it_CH)
- ✅ Kompatibel mit **HPOS** (High-Performance Order Storage)
- ✅ **Updates über das offizielle WordPress-Plugin-Verzeichnis**
- ✅ Kein Build-Step, kein Tracking, keine externen Aufrufe

## Screenshots

**Block-Checkout** – TWINT mit Pflichtfeld für die Handynummer (Ablauf «Ich fordere an»):

![Block-Checkout mit TWINT und Handynummer-Feld](docs/screenshots/checkout-block.png)

**Einstellungen** – Ablauf, Nummer, Kontoinhaber und QR-Bild-Auswahl aus der Mediathek:

![TWINT-Einstellungen im WooCommerce-Backend](docs/screenshots/admin-settings.png)

## Installation

**Empfohlen – aus dem WordPress-Verzeichnis:** Im Backend unter **Plugins → Installieren** nach **«Blueforce Manual Payments for TWINT»** suchen, installieren und aktivieren. Oder direkt über [wordpress.org/plugins/blueforce-manual-payments-for-twint](https://wordpress.org/plugins/blueforce-manual-payments-for-twint/).

Anschliessend unter **WooCommerce → Einstellungen → Zahlungen → TWINT** aktivieren und konfigurieren.

**Voraussetzungen:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+.

## Updates

Das Plugin liegt im **offiziellen WordPress-Plugin-Verzeichnis**. Updates erscheinen – wie bei jedem anderen Verzeichnis-Plugin – automatisch unter **Plugins** und lassen sich mit einem Klick oder per Auto-Update installieren. Das Plugin selbst macht **keine externen Aufrufe** und kontaktiert keine fremden Server.

## Konfiguration

| Einstellung | Beschreibung |
| --- | --- |
| **Ablauf** | «Kunde sendet» oder «Ich fordere an» |
| **Deine TWINT-Handynummer** | Nummer, an die der Kunde sendet (Ablauf «Kunde sendet») |
| **Name des Kontoinhabers** | Optional, zur Kontrolle für den Kunden |
| **TWINT-QR-Bild** | Optional: lade in der TWINT-App unter «Geld empfangen» deinen QR-Code, speichere ihn als Bild und wähle ihn per Button direkt aus der Mediathek |
| **Zusätzliche Hinweise** | Freitext für Danke-Seite und E-Mail |

## Datenschutz

Im Ablauf **«Ich fordere an»** gibt der Kunde im Checkout seine TWINT-Handynummer an. Diese wird als Bestell-Metadatum (`_bf_twint_customer_phone`) gespeichert und dient ausschliesslich dazu, die Zahlung in der TWINT-App anzufordern – sie ist personenbezogen.

Das Plugin bindet diese Nummer in die WordPress-/WooCommerce-Datenschutzwerkzeuge ein:

- **Datenexport** (Werkzeuge → Personenbezogene Daten exportieren): Die Nummer wird mit ausgegeben.
- **Datenlöschung** (Werkzeuge → Personenbezogene Daten entfernen): Die Nummer wird beim Anonymisieren der Bestellung entfernt.
- **Datenschutzerklärung**: Unter Einstellungen → Datenschutz steht ein vorformulierter Textbaustein bereit.

Im Ablauf **«Kunde sendet»** werden keine personenbezogenen Zahlungsdaten des Kunden erfasst.

## Mitwirken

Issues und Pull Requests sind willkommen. Versionsschema (`MAJOR.MINOR.MAINTENANCE`), Release-Prozess und Changelog-Konvention sind in **[CONTRIBUTING.md](CONTRIBUTING.md)** beschrieben; Code-Stil: WordPress Coding Standards.

## Lizenz

[GPL-2.0-or-later](LICENSE) © [Blueforce Digital Solutions](https://blueforce.ch)

## Disclaimer

Dieses Plugin ist ein unabhängiges Community-Projekt und steht **in keiner Verbindung zur TWINT AG**. «TWINT» ist eine eingetragene Marke der TWINT AG und wird hier ausschliesslich zur Beschreibung der Kompatibilität verwendet.

## Support

Fragen oder Anliegen: [info@blueforce.ch](mailto:info@blueforce.ch) · [blueforce.ch](https://blueforce.ch)
