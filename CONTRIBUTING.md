# Mitwirken & Versionierung

Danke für dein Interesse an *TWINT for WooCommerce*. Dieses Dokument hält fest, **wie versioniert wird**, **wie ein Release entsteht** und **wie der Changelog gepflegt wird**.

## Versionsschema

Das Plugin folgt **[Semantic Versioning](https://semver.org/lang/de/)** im Format `MAJOR.MINOR.MAINTENANCE` (z. B. `1.4.2`):

| Stelle | Beispiel | Name | Wann erhöhen |
| --- | --- | --- | --- |
| 1. Stelle | `1.0.0` → **`2`**`.0.0` | **Major** | Bei **inkompatiblen Änderungen** – etwas funktioniert nach dem Update nicht mehr wie vorher (z. B. geänderte Gateway-ID, entfernte Einstellungen, neue Mindestanforderungen an WP/WC/PHP). |
| 2. Stelle | `1.`**`0`**`.0` → `1.`**`1`**`.0` | **Minor** | Bei **neuen Funktionen**, die **abwärtskompatibel** sind (z. B. ein neues Einstellungsfeld, ein neuer Ablauf). Bestehende Installationen laufen unverändert weiter. |
| 3. Stelle | `1.0.`**`0`** → `1.0.`**`1`** | **Maintenance** (Patch) | Bei **Fehlerbehebungen** und kleinen Korrekturen ohne neue Funktionen (Bugfix, Sicherheitsfix, Doku-/Übersetzungskorrektur). |

**Regeln:**

- Wird eine höhere Stelle erhöht, werden die tieferen auf `0` zurückgesetzt: nach `1.4.2` kommt das nächste Feature als `1.5.0`, der nächste Breaking Change als `2.0.0`.
- **Im Zweifel** lieber eine Stelle höher: Ändert sich für bestehende Shops irgendetwas am Verhalten, ist es mindestens **Minor**, bei Bruch **Major**.
- Die Versionsnummer muss an **allen** Stellen identisch sein (siehe Release-Prozess).

## Release-Prozess

1. **Version erhöhen** an genau diesen vier Stellen (müssen übereinstimmen):
   - `twint-for-woocommerce.php` → Header `Version:` **und** Konstante `BF_TWINT_VERSION`
   - `readme.txt` → `Stable tag:`
   - `CHANGELOG.md` → neuer Versionsabschnitt (siehe unten)
2. **Committen** und Tag setzen:
   ```bash
   git commit -am "x.y.z: <kurzbeschrieb>"
   git tag -a x.y.z -m "TWINT for WooCommerce x.y.z"
   git push origin main && git push origin x.y.z
   ```
3. **Release-ZIP bauen:**
   ```bash
   ./build.sh x.y.z
   ```
   Das erzeugt `twint-for-woocommerce.zip` mit korrektem Ordnernamen und prüft, dass alle Pflicht-Bestandteile (inkl. Update-Bibliothek) enthalten sind.
4. **GitHub-Release veröffentlichen** – das ZIP **muss** als Asset angehängt sein:
   ```bash
   gh release create x.y.z twint-for-woocommerce.zip --title "x.y.z – <Titel>" --notes "..."
   ```
   > ⚠️ Ohne angehängtes `twint-for-woocommerce.zip` findet der [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) nichts – die 1-Klick-Aktualisierung im Backend würde dann nicht funktionieren.

## Changelog pflegen

- Format: **[Keep a Changelog](https://keepachangelog.com/de/1.0.0/)**.
- Jede Änderung kommt **zuerst** unter den Abschnitt `## [Unreleased]` in `CHANGELOG.md`, gruppiert nach: `Hinzugefügt`, `Geändert`, `Behoben`, `Entfernt`, `Sicherheit`.
- Beim Release wird `[Unreleased]` in den neuen Versionsabschnitt mit Datum umbenannt (`## [x.y.z] – YYYY-MM-DD`) und ein frischer, leerer `[Unreleased]`-Abschnitt darüber angelegt.
- Knapp die Kund:innen-relevanten Änderungen in `readme.txt` unter `== Changelog ==` spiegeln (das ist der Text, den WordPress im Backend anzeigt).

## Code-Stil

- WordPress Coding Standards (Tabs, `esc_*`/`wp_kses_*` beim Ausgeben, Text-Domain `twint-for-woocommerce` bei allen Strings).
- Keine externen Laufzeit-Abhängigkeiten ausser der eingebetteten Update-Bibliothek; kein Build-Step fürs Plugin selbst.

## Lokale Qualitätsprüfung

Die Coding Standards lassen sich vor dem Commit lokal prüfen (dieselbe Konfiguration wie in der CI, siehe `phpcs.xml.dist`). Die Tools sind reines Entwicklungs-Tooling und werden **nicht** mit dem Plugin ausgeliefert:

```bash
composer install      # einmalig: PHPCS, WPCS und PHPCompatibilityWP holen
composer phpcs        # WordPress Coding Standards / PHP-Kompatibilität prüfen
composer phpcbf       # automatisch behebbare Verstösse korrigieren
composer lint         # reine PHP-Syntaxprüfung
```

Die GitHub-Action (`.github/workflows/ci.yml`) führt dieselben Checks plus den ZIP-Build-Test bei jedem Push/PR aus.

Fragen? [info@blueforce.ch](mailto:info@blueforce.ch)
