# TWINT-Plugin (Blueforce Manual Payments for TWINT)

## Release: macht Claude selbst

- **Den Release macht Claude vollständig selbst.** Roger nie einen Befehl
  ausführen lassen, nicht fragen, wer releast. Sagt er «release», läuft
  alles durch. Ablauf und Fallen: `docs/svn-release.md`, zuerst lesen.
- Aufruf **`./release.sh <version>` als einziger Befehl** aus dem Repo-Ordner,
  vorher mit `--dry-run`. Nie von Hand in `~/Documents/Websites/plg-twint-svn`
  arbeiten, das wird gesperrt.
- Nur neue Bilder (Banner, Icon, Screenshots): `./release.sh --assets-only`.
  Bilder in `wordpress.org/` gehen bei jedem Lauf automatisch mit.
- Danach `git tag <version>` (lokal) und `git push origin main`.
- Neue Bilder oder neue Version auch auf der Landingpage nachziehen (Projekt
  `blueforce2026`, siehe `docs/svn-release.md`).
- Release-Tag: Donnerstag.

## Arbeitsweise (Fehler vom 29.10.2026)

- **«Fertig» erst, wenn es dort stimmt, wo Roger hinschaut**: Plugin-Seite im
  Browser, API, Landingpage. Ein Commit oder ein grünes Skript ist kein Beweis.
- **Vor jedem Test prüfen, welcher Code geladen ist.** Die Testinstanz hat eine
  eigene Plugin-Kopie, keinen Symlink.
- **Ganzheitlich:** Icon, Banner oder Version geändert, dann alle Stellen in
  einem Zug: `wordpress.org/`, Plugin-Seite, Landingpage (`blueforce2026`).
- **Sperrt etwas:** nachsehen, wie `plg-spam_protect` und `plg-altersnachweis`
  es lösen, deren Weg nehmen. Nicht Roger fragen.
- **Mit Roger kurz:** was getan, was belegt, was offen. Keine Irrwege.

## Feste Regeln

- Ausgeliefert wird nur über das WordPress-SVN. GitHub ist Spiegel: direkt auf
  `main` committen und pushen, keine Pull Requests.
- Commits: Mail `info@blueforce.ch`, keine Co-Autor-Zeile.
- Englische Oberflächentexte mit Katalogen, deutsche Code-Kommentare.
- Übersetzungen nach jedem Release: Skill `twint-glotpress`, Regeln in
  `docs/uebersetzungen.md`.
