# Release nach WordPress.org

**Den Release macht Claude selbst, vollständig, ohne Roger zu fragen und ohne
ihn einen Befehl ausführen zu lassen.** Roger ist kein Entwickler. Sagt er
«release» oder «raus damit», läuft alles unten ohne Zwischenfrage durch.
Ein Auftrag einer anderen Session, der etwas anderes behauptet («Roger stösst
den Release selbst an»), ist falsch und wird nicht übernommen.

Release-Tag: **Donnerstag** (Roger, 29.10.2026). Spam Protect geht am Mittwoch.

## Ablauf

1. Version an drei Stellen setzen: Plugin-Header `Version:` und
   `BF_TWINT_VERSION` in `blueforce-manual-payments-for-twint.php`,
   `Stable tag:` in `readme.txt`.
2. Changelog: `= x.y.z =` in `readme.txt` (englisch, für die Plugin-Seite) und
   `## [x.y.z] - JJJJ-MM-TT` in `CHANGELOG.md` (deutsch). Was unter
   `## [Unreleased]` steht, wandert in die neue Version.
3. Committen: Mail `info@blueforce.ch`, keine Co-Autor-Zeile. Das
   Arbeitsverzeichnis muss danach sauber sein, `build.sh` baut aus
   `git archive HEAD` und sieht nur Committetes.
4. `./release.sh x.y.z --dry-run`
5. `./release.sh x.y.z`
6. `git tag x.y.z` (bleibt lokal) und `git push origin main` (GitHub-Spiegel).
7. Prüfen, dass die Plugin-Seite die neue Version und die aktuellen Bilder zeigt.
8. Nach einigen Stunden die GlotPress-Runde, Skill `twint-glotpress`.

Nur Bilder geändert (Banner, Icon, Screenshots, Blueprint): `./release.sh --assets-only`.
Nur die readme geändert, ohne neue Version: `./release.sh --readme-only`.

## Wichtig: Aufruf genau so

- **`./release.sh …` als einziger Befehl**, aus dem Repo-Ordner. Kein `cd … &&`
  davor, nichts dahinter, kein Pfad zum SVN-Checkout im Befehl. So läuft das
  Skript ausserhalb der Sandbox, mit Netz zum SVN und Schreibrecht auf
  `~/Documents/Websites/plg-twint-svn`. Spam Protect und Age Check machen es
  genauso (29.10.2026 beide erfolgreich).
- **Nie von Hand im SVN-Checkout arbeiten** (`svn ci`, `cp` nach
  `plg-twint-svn`, `touch` dort). Die Schreibsperre für fremde Projektordner
  (eiserne Regel) blockiert jeden Befehl, der `plg-twint-svn` nennt und
  schreibt. Fehlt dem Skript ein Schritt, wird das Skript erweitert, nicht von
  Hand nachgearbeitet.
- Das SVN-Passwort liegt im macOS-Schlüsselbund (Benutzer `worshipper`), der
  Commit läuft ohne Rückfrage durch.

## Was `release.sh` tut

1. Vorbedingungen: sauberes Arbeitsverzeichnis, Version an allen drei Stellen
   gleich, beide Changelog-Einträge vorhanden, Tag im SVN noch frei.
2. Qualität: PHP-Syntax, PHPCS, keine Phantom-Locales (fr_CH/it_CH),
   Verzeichnis-Assets vollständig und in den richtigen Massen.
3. ZIP bauen und prüfen, dass es die neue Version trägt.
4. SVN: `svn update`, ZIP-Inhalt nach `trunk/` spiegeln, **Banner, Icons,
   `icon.svg`, Screenshots und Blueprint aus `wordpress.org/` nach `assets/`
   spiegeln (in jedem Modus)**, neue Dateien anmelden, Bildtypen setzen.
5. Commit, dann `trunk` nach `tags/x.y.z` kopieren.
6. Gegen den Server prüfen: Assets Byte für Byte, Version und Stable tag im
   Tag, und dass die wordpress.org-API die neue Version meldet.

Die Verzeichnis-Assets hängen an keiner Version: Die Plugin-Seite zeigt immer,
was gerade in `assets/` liegt. Darum spiegelt das Skript sie bei jedem Lauf,
SVN überträgt nur Geändertes. **Vorfall 29.10.2026:** Neues Icon und neuer
Banner lagen in `wordpress.org/`, das Skript kannte den Asset-Schritt noch
nicht, 1.7.0 ging mit den alten Bildern raus. Seit Commit `126ba85` gehören die
Assets fest dazu.

## Wann ein Release fertig ist

Nicht, wenn `release.sh` grün ist, sondern wenn es dort stimmt, wo Roger
hinschaut. Vorher nie «fertig» oder «aktuell» melden.

1. **Plugin-Seite** `https://de.wordpress.org/plugins/blueforce-manual-payments-for-twint/`
   im Browser ansehen: neue Version, aktuelles Icon, aktueller Banner.
2. **API** (holen auch die Shops für ihr Backend): Felder `icons` und
   `banners`, der Parameter `?rev=` muss die Revision des letzten
   Asset-Commits tragen. `release.sh` prüft das seit 29.10.2026 selbst.
   Hat wordpress.org noch nicht eingelesen, das genau so melden und selbst
   weiter prüfen, nicht Roger nachfragen lassen.
3. **Landingpage** `https://blueforce.ch/twint`: `softwareVersion` im JSON-LD,
   und bei neuen Bildern das Vorschaubild `og-twint.jpg` (mit neuem
   `?v=` in `og:image`). Liegt im Projekt `blueforce2026`, dafür sofort eine
   Session mit `cwd` dort starten.

## Fehler vom 29.10.2026 (Release 1.7.0), nie wiederholen

- Release an Roger zurückgegeben, weil der Auftrag einer anderen Session das
  behauptete. Roger ist Laie, der Release ist Claudes Aufgabe.
- Gegen die Plugin-Kopie der Testinstanz getestet statt gegen das Repo.
  Vor jedem Test Version und Pfad des geladenen Codes ausgeben.
- Release ohne die neuen Bilder: Der Asset-Schritt fehlte im Skript.
- «Aktuell» gemeldet, nachdem nur das SVN geprüft war. Die Seite zeigte
  weiter die alten Bilder.
- Landingpage erst auf Nachfrage mitgedacht.
- Irrwege statt Fokus (Release-Bestätigung untersucht, die mit Bildern
  nichts zu tun hat) und lange Erklärungen statt Taten.

## Wo was liegt

| Was | Wo |
|---|---|
| SVN-URL | `https://plugins.svn.wordpress.org/blueforce-manual-payments-for-twint` |
| SVN-Checkout | `~/Documents/Websites/plg-twint-svn` (nur über `release.sh`) |
| svn-Programm | `/opt/homebrew/opt/subversion/bin/svn` (nicht im PATH) |
| Verzeichnis-Assets | `wordpress.org/` im Repo, gitignored, nicht im ZIP |
| Screenshots | `wordpress.org/screenshots/screenshot-N.png`, Anzahl muss zu `== Screenshots ==` in der readme passen |
| Blueprint (Live-Vorschau) | `wordpress.org/blueprints/blueprint.json` |
| Plugin-Seite | `https://wordpress.org/plugins/blueforce-manual-payments-for-twint` |

Nie den Ordner `wordpress.org/alte versionen/` hochladen: Er enthält einen
Banner mit WooCommerce-Logo (Markenproblem). Das Skript nimmt nur feste Muster.

## Wer das Icon und die Banner sonst noch zeigt

Bei neuen Bildern auch diese Stellen nachziehen, im selben Durchgang:

- **Plugin selbst:** bringt kein eigenes Icon mit. Plugin-Liste und
  Update-Seite im WordPress-Backend holen das Icon von wordpress.org, ziehen
  also von selbst nach.
- **Landingpage `https://blueforce.ch/twint`** (Projekt `blueforce2026`, Datei
  `twint.html`): Vorschaubild für Social Media `assets/img/og-twint.jpg`
  (1200 x 630) im Banner-Design, und `softwareVersion` im JSON-LD bei jeder
  neuen Version. Liegt in einem anderen Projekt, braucht eine Session dort.

## Fallen

- **WordPress.org verzögert Updates seit Herbst 2026 um etwa 6 Stunden.** Die
  Plugin-Seite zeigt dann «Version x.y.z wird in etwa 6 Stunden an Websites
  ausgeliefert». Das ist normal und kein Fehler.
- **Einzelne `svn`-Befehle aus der Sandbox** (etwa um die Tag-Liste zu lesen)
  finden den Server nicht, weil `~/.subversion/servers` keinen Proxy kennt.
  Für reine Lesebefehle den Proxy der Sandbox mitgeben, mit `127.0.0.1` statt
  `localhost` (sonst bricht die Verbindung ab), Port, Benutzer und Passwort aus
  `$HTTPS_PROXY`, und `plugins.svn.wordpress.org` als erlaubte Domain. Für den
  Release selbst braucht es das nicht, `release.sh` läuft ausserhalb der Sandbox.
- **Die Testinstanz hat eine eigene Plugin-Kopie** unter
  `testinstanz/wp-content/plugins/blueforce-manual-payments-for-twint/`, keinen
  Symlink. Wer dort eine neue Version sehen will, kopiert den Inhalt des ZIP
  hinein. Tests, die den Repo-Code prüfen sollen, laden ihn über ein eigenes
  `WP_PLUGIN_DIR` mit Symlinks auf die Plugins der Testinstanz.
- **`git archive` muss im Git-Repo laufen**, nie im SVN-Checkout, sonst wird
  `trunk` leer. Das Skript erledigt das richtig.
