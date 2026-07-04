# SVN-Release nach WordPress.org

Ablauf für die Veröffentlichung im Plugin-Verzeichnis. WordPress.org-SVN ist ein
**Release-System** (kein Git): nur fertige, geprüfte Stände committen.

- **SVN-URL:** `https://plugins.svn.wordpress.org/blueforce-manual-payments-for-twint`
- **Username:** `worshipper` (case-sensitive)
- **Passwort:** separates SVN-Passwort aus profiles.wordpress.org/me/profile/edit/group/3/ (NICHT das WP.org-Login)
- **Offizielle Anleitung:** https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

Voraussetzungen einmalig: `brew install subversion`, SVN-Passwort gesetzt, Commit-Zugang aktiv (bis 1 h nach Approval).

Der Stable tag in `trunk/readme.txt` steht bereits auf **1.4.3** und die Plugin-Header-Version ebenfalls — sobald `tags/1.4.3/` existiert, liefert das Directory diese Version aus.

---

## Schritt 1 — SVN-Repo auschecken (ausserhalb des Git-Repos)

```bash
cd ~/Documents/Websites
svn checkout https://plugins.svn.wordpress.org/blueforce-manual-payments-for-twint plg-twint-svn
cd plg-twint-svn
# Leeres Gerüst: trunk/ branches/ tags/ assets/
```

## Schritt 2 — trunk mit dem sauberen Plugin-Stand füllen

`git archive` liefert exakt den ZIP-Inhalt (respektiert `.gitattributes export-ignore`:
kein README.md/CHANGELOG.md/vendor/docs/.github …, aber MIT composer.json und uninstall.php).

```bash
# Vorhandenen trunk-Inhalt leeren (beim Erstrelease ist er leer)
rm -rf trunk/*
git -C ~/Documents/Websites/plg-twint archive HEAD | tar -x -C trunk/
ls trunk/    # Kontrolle: Hauptdatei, readme.txt, includes/, assets/, languages/, composer.json, uninstall.php
```

## Schritt 3 — Verzeichnis-Assets (Banner/Icons) einspielen

**Wichtig:** nur die finalen Assets — den Ordner `wordpress.org/alte versionen/` NICHT
kopieren (enthält eine Banner-Variante mit WooCommerce-Logo → Trademark-Problem).

```bash
A=~/Documents/Websites/plg-twint/wordpress.org
cp "$A/banner-772x250.png"  assets/
cp "$A/banner-1544x500.png" assets/
cp "$A/icon-128x128.png"    assets/
cp "$A/icon-256x256.png"    assets/
cp "$A/icon.svg"            assets/
ls assets/
```

## Schritt 4 — Neue Dateien für SVN vormerken

```bash
svn add trunk/* assets/* --force
svn status        # A = added; kontrollieren, dass alles Gewünschte dabei ist
```

## Schritt 5 — trunk + assets committen

```bash
svn ci -m "Initial release 1.4.3" --username worshipper
# SVN-Passwort eingeben, wenn gefragt
```

## Schritt 6 — Version taggen (serverseitige Kopie, effizient)

```bash
svn cp \
  https://plugins.svn.wordpress.org/blueforce-manual-payments-for-twint/trunk \
  https://plugins.svn.wordpress.org/blueforce-manual-payments-for-twint/tags/1.4.3 \
  -m "Tag 1.4.3" --username worshipper
```

---

## Danach

- Öffentliche Seite erscheint nach wenigen Minuten: https://wordpress.org/plugins/blueforce-manual-payments-for-twint (Suche/Profil bis 72 h).
- **Kontrolle:** Seite lädt, Banner/Icon sichtbar, Version 1.4.3, Beschreibung korrekt.
- readme-Validator gegen die Live-Seite: https://wordpress.org/plugins/developers/readme-validator/

## Künftige Updates (nach dem ersten Release)

1. Version bumpen (Header `Version`, `BF_TWINT_VERSION`, readme `Stable tag`, Changelog), git-committen.
2. `rm -rf trunk/* && git archive HEAD | tar -x -C trunk/` → `svn add --force` → gelöschte per `svn rm` → `svn ci`.
3. `svn cp .../trunk .../tags/<version>`.
   (Dann lohnt sich ein `svn-deploy.sh`, das das kapselt — analog zu build.sh.)
