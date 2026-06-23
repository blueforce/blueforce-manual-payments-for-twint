# TWINT for WooCommerce

[![Version](https://img.shields.io/github/v/release/blueforce/twint-for-woocommerce?label=Version)](https://github.com/blueforce/twint-for-woocommerce/releases)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588a.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)

Ein kostenloses, natives WooCommerce-Bezahl-Gateway für **TWINT** – **ohne API, ohne Vertrag mit TWINT und ohne Payment Service Provider**. Entwickelt und bereitgestellt von [Blueforce Digital Solutions](https://blueforce.ch).

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
- ✅ Vollständig übersetzbar (Text-Domain `twint-for-woocommerce`, deutsche Strings)
- ✅ Kompatibel mit **HPOS** (High-Performance Order Storage)
- ✅ **Automatische Updates direkt im WordPress-Backend** (1-Klick, ohne wordpress.org)
- ✅ Kein Build-Step, keine Tracking-Aufrufe

## Screenshots

**Block-Checkout** – TWINT mit Pflichtfeld für die Handynummer (Ablauf «Ich fordere an»):

![Block-Checkout mit TWINT und Handynummer-Feld](docs/screenshots/checkout-block.png)

**Einstellungen** – Ablauf, Nummer, Kontoinhaber und QR-Bild-Auswahl aus der Mediathek:

![TWINT-Einstellungen im WooCommerce-Backend](docs/screenshots/admin-settings.png)

## Installation

1. Das **[aktuelle Release-ZIP](https://github.com/blueforce/twint-for-woocommerce/releases/latest)** herunterladen (Datei `twint-for-woocommerce.zip` unter «Assets»).
2. In WordPress unter **Plugins → Installieren → Plugin hochladen** einspielen und aktivieren.
3. Unter **WooCommerce → Einstellungen → Zahlungen → TWINT** aktivieren und konfigurieren.

**Voraussetzungen:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+.

> ⚠️ Lade das **Release-Asset** `twint-for-woocommerce.zip` herunter – **nicht** den grünen «Code → Download ZIP»-Button. Der Quellcode-Download enthält einen falsch benannten Ordner und die Update-Bibliothek wird nicht ausgeliefert.

## Automatische Updates

Obwohl das Plugin nicht im wordpress.org-Verzeichnis liegt, erhältst du Updates **direkt im Backend** – wie bei jedem anderen Plugin:

- Sobald ein neues Release (z. B. `1.0.1`) veröffentlicht wird, erscheint unter **Plugins** der Hinweis «Es ist eine neue Version verfügbar».
- Mit **«Jetzt aktualisieren»** wird die neue Version mit einem Klick installiert.
- Optional lassen sich unter **Plugins** auch **automatische Updates aktivieren**.

Technisch übernimmt das der eingebettete [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), der die GitHub-Releases dieses Repos prüft. Dazu ruft deine WordPress-Installation periodisch die **öffentliche GitHub-Releases-API** auf (`api.github.com`) – wie bei jeder Update-Prüfung wird dabei die Server-Anfrage an GitHub übermittelt. Es werden **keine personenbezogenen Kunden- oder Bestelldaten** gesendet. Wer keinen externen Update-Request möchte, kann den Update-Checker per Filter deaktivieren oder das Plugin manuell aktualisieren.

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
