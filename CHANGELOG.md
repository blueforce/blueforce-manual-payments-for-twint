# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

## [1.0.0] – 2026-06-21

### Hinzugefügt
- TWINT-Bezahl-Gateway für WooCommerce (manuelles Verfahren, ohne API/Vertrag).
- Ablauf «Kunde sendet»: Anzeige von TWINT-Handynummer, Kontoinhaber und optionalem QR-Code.
- Ablauf «Ich fordere an»: Pflichtfeld für die TWINT-Handynummer des Kunden.
- Unterstützung für klassischen Checkout und Block-Checkout (Store-API).
- Anweisungen auf Danke-Seite, in der Bestell-E-Mail und im Backend.
- HPOS-Kompatibilität.
- Vollständige Übersetzbarkeit (Text-Domain `twint-for-woocommerce`) inkl. mitgelieferter
  Übersetzungen für **de_DE**, **en_GB** und **en_US** (`.po`/`.mo`) sowie JS-Übersetzungen
  (`.json`) für den Block-Checkout.
