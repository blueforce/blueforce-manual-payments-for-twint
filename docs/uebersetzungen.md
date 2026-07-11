# Übersetzungen (i18n) – Anleitung für künftige Releases

Wie die Übersetzungen dieses Plugins funktionieren und wie man sie auf
translate.wordpress.org pflegt. Stand: 2026-07-05 (nach dem ersten vollständigen
GlotPress-Import).

## Grundprinzip

Seit **v1.5.0** nutzt das Plugin **englische Quell-Strings** (`msgid`). Alle
Sprachen sind echte Übersetzungen. Das ist die Voraussetzung dafür, dass
translate.wordpress.org (GlotPress) die Übersetzung überhaupt verarbeitet – mit
deutschen Quell-Strings ging das nicht.

Es gibt **zwei parallele Übersetzungs-Kanäle**:

1. **Gebündelt im Plugin** (`languages/*.po/.mo/.json`) – greift lokal bei jeder
   Installation, auch offline.
2. **WordPress.org-Sprachpakete** – werden aus den freigegebenen GlotPress-Strings
   gebaut und über den WP-Update-Flow ausgeliefert.

**Vorrang:** Das WordPress.org-Paket landet in `wp-content/languages/plugins/` und
**gewinnt** gegen die gebündelte Datei im Plugin-Ordner. Sobald ein Sprachpaket
existiert, sehen Nutzer also die GlotPress-Version, nicht die gebündelte.

## de_DE vs. de_CH – die «ß»/«ss»-Regel

- **de_DE** (Deutschland) verlangt **«ß»**. Das ist die **Hauptvariante** – fast
  alle deutschsprachigen Nutzer, auch Schweizer, haben WordPress auf «Deutsch»
  (de_DE), nicht «Deutsch (Schweiz)» (de_CH).
- **de_CH** (Schweiz) nutzt **«ss»**. Wird vom **Konvertierungs-Skript des de_CH-
  Teams** automatisch aus de_DE abgeleitet – man muss de_CH nicht selbst pflegen.
- Praktisch heisst das: **de_DE mit «ß» pflegen**, de_CH dem Team überlassen.

**Getrennt seit `[Unreleased]` (geht mit 1.5.1 live):** Die gebündelten Dateien
sind jetzt sauber getrennt – `…-de_DE.po/.mo` («ß») + `…-de_CH.po/.mo/.json` («ss»).
Zuvor lag unter de_DE die «ss»-Variante (faktisch de_CH). Die de_CH-Dateien wurden
aus der alten de_DE abgeleitet; für de_DE wurde nur **ein** Wort auf «ß» korrigiert
(«ausschliesslich» → «ausschließlich»; die übrigen Strings haben kein scharfes s).
Die JS-`.json` enthält keine ss/ß-Wörter → `de_CH`-json = Kopie der `de_DE`-json.
Kein Code-Change nötig – WordPress lädt `de_CH` automatisch über den Domain-Path.

## de_DE: zwei Zweige (du / Sie)

Deutsch hat auf GlotPress zwei Zweige:

- **default** = «du» (informell) → **Hauptvariante**, die die meisten bekommen.
- **formal** = «Sie» (formell).

Beide sind gepflegt. Für ein Checkout-Plugin ist «Sie» durchaus relevant, darum
wurde auch der formal-Zweig befüllt (komplette du→Sie-Konvertierung).

**Die Sie-Fassung lebt im Repo:** `languages/formal/…-de_DE_formal.po`
(per `.gitattributes` vom Auslieferungs-ZIP ausgeschlossen – sie existiert nur
für den GlotPress-Import). Bei jedem neuen String wird sie **zusammen mit den
gebündelten `.po` gepflegt**; die GlotPress-Lieferung ist dann eine Kopie
dieser Datei, kein Neubau. Achtung bei der Konvertierung: Der Ablaufname
«Ich fordere an» ist die Ich-Perspektive des Shops, keine Kundenanrede.

## Eiserne Import-Regeln (nach den Vorfällen vom 2026-07-12)

GlotPress-Importe überschreiben kommentarlos als «current» und überspringen
unbekannte msgids **stillschweigend**. Daraus folgen fünf Regeln:

1. **Nie Deltas – immer Komplett-Dateien pro Zweig.** Ein Import stellt so
   deterministisch den ganzen Zweig richtig. (Vorfall: eine Komplett-du-Datei
   wurde in den formal-Zweig importiert und überschrieb dort alle
   Sie-Übersetzungen – Delta-Lieferungen hatten die Verwechslung begünstigt.)
2. **Eine Datei = genau ein Import-Ziel, Ziel im Dateinamen.**
   Konvention: `twint-<version>-<code|readme>-<locale>-<default|formal>.po`.
   Nie eine Datei «für beide Zweige» deklarieren, auch bei identischem Inhalt.
3. **msgid-Quelle ist immer der GlotPress-Export**
   (`…/export-translations/?format=po`, Fehlbestände mit
   `&filters[status]=untranslated`) – nie der Wortlaut aus Repo oder readme
   (HTML-Entities, Markdown→HTML und Quotes weichen sonst ab).
4. **Erst Sync abwarten, dann importieren.** Nach einem SVN-Release dauert es
   Stunden, bis GlotPress die neuen Strings eingelesen hat. Ein Import davor
   läuft für die neuen Strings ins Leere (sie werden kommentarlos übersprungen).
   Prüfen: erscheint der neue String im untranslated-Export?
5. **Nach dem Import verifizieren** (Prozent-Stand je Zweig bzw.
   untranslated-Export) und **die Import-Dateien vom Desktop löschen** –
   liegengebliebene alte Dateien sind die nächste Verwechslungsquelle.

## PTE-Status und Import

- **PTE (Project Translation Editor)** für **de_DE** ist vergeben (Account
  `worshipper`, freigegeben 2026-07-05 vom deutschen Team).
- **Schneller Weg zur PTE-Freigabe:** deutscher Slack `dewp.slack.com`, Channel
  **#polyglots_dach** (der offizielle make.wordpress.org/polyglots-Thread war zäh).
- Als PTE importiert man `.po`-Dateien direkt als **«current»** (sofort gültig):
  Projektseite → unten **«Import Translations»** → `.po` wählen → Format PO.
- **In beide Sub-Projekte importieren:** `stable` (aktuelles Release) **und** `dev`
  (Entwicklung), jeweils `de/default` und `de/formal`.

### fr / it: nur Vorschläge

Für **Französisch** und **Italienisch** besteht **kein PTE**. Deshalb:

- Import als **«waiting»** (Vorschläge). Ein fr-/it-**GTE** gibt sie frei.
- Locale-Ziel: **fr/default** (= French France) und **it/default** (= Italian).
  Es gibt **kein** eigenes fr_CH/it_CH-Projekt – Romandie/Tessin landen bei
  fr_FR/it_IT (geschriebenes CH-Französisch/Italienisch = FR/IT).
- fr-/it-PTE bewusst **nicht** beantragt: kein Muttersprachler → der GTE-Review ist
  der bessere Qualitäts-Weg.

## Readme (Plugin-Verzeichnis-Seite)

Die Verzeichnis-Seite kommt aus der `readme.txt` und ist ein **separates
GlotPress-Projekt** («Stable Readme» / «Development Readme»), getrennt von den
Code-Strings.

- Übersetzt werden nur die **inhaltlichen Abschnitte** (Beschreibung, Abläufe,
  Features, Installation, FAQ, Datenschutz). Der **Changelog bleibt englisch** –
  Konvention im ganzen Verzeichnis, fällt automatisch auf Englisch zurück. Ein
  vollständig übersetztes Readme erreicht darum nur ~44 %; das ist der Soll-Zustand.
- **Matching-Fallstrick beim Import:** WordPress.org wandelt Markdown in der
  `readme.txt` (`**fett**`, `` `code` ``) in **HTML** um (`<strong>`, `<code>`).
  Eine `.po` mit Markdown-`msgid` **matcht nicht** – die betroffenen Strings mit
  `<strong>`/`<code>` in der `msgid` bauen. Plain-Text und `"..."`-Strings matchen
  problemlos.

## Sprachpaket-Auslieferung

- WordPress.org baut aus den **freigegebenen (current)** Code-Strings automatisch
  ein Sprachpaket – **nicht sofort**, der Build läuft periodisch (Stunden bis ~1 Tag).
- Auslieferung im Backend: **Dashboard → Aktualisierungen → «Übersetzungen
  aktualisieren»** (oder automatisch per WP-Cron).
- **Nur freigegebene Strings zählen** – fr/it (waiting) erzeugen erst nach GTE-
  Freigabe ein Paket.
- Die Readme-Übersetzung (44 %) betrifft **nur die Verzeichnis-Seite**, nicht das
  Backend-Sprachpaket.

## Checkliste beim nächsten Release

1. `.pot` neu erzeugen, gebündelte `.po` gegen die aktuelle `.pot` abgleichen.
2. Neue/geänderte Strings in **de_DE** (ß, du + Sie) übersetzen und als PTE nach
   GlotPress importieren (stable **und** dev).
3. fr/it neue Strings als **waiting** nachreichen.
4. Readme-Änderungen ins «Readme»-Projekt (HTML-Matching beachten).
5. ✅ Gebündelte Dateien sind auf `de_DE`(ß) + `de_CH`(ss) getrennt (seit 1.5.1).
