# Roadmap TWINT-Plugin

Stand: 26.11.2026 · live ist Version 1.7.1, getestet bis WordPress 7.2

Diese Liste ist die einzige Sammelstelle für nächste Schritte. Was erledigt
ist, wandert in den `CHANGELOG.md` und verschwindet hier.

## Ausgangslage

- Keine offenen GitHub-Issues, keine Threads im Support-Forum auf
  wordpress.org (geprüft 26.11.2026).
- Alle früher offenen Punkte (Verzeichnis-Assets, Screenshots, Live-Vorschau,
  Plugin-URI auf die Landingpage) sind erledigt.
- Es gibt keinen Druck von aussen. Neue Funktionen kommen nur, wenn eine
  echte Rückmeldung sie begründet.

## Laufende Pflege

| Was | Wann | Wie |
|---|---|---|
| «Tested up to» auf neue WordPress-Version | bei jeder Hauptversion (nächste: 7.3) | Plugin Check und Laufzeittest unter dem RC, dann `./release.sh --readme-only` |
| «WC tested up to» im Plugin-Kopf | beim nächsten echten Release | steht im Code, geht nur mit neuer Version raus |
| Übersetzungen de | nach jedem Release mit neuen Texten | Skill `twint-glotpress`, Regeln in `docs/uebersetzungen.md` |
| Release-Tag | Donnerstag | `docs/svn-release.md` |

## Kandidaten

### 1. «WC tested up to» von 11.0 auf die aktuelle WooCommerce-Version

- Heute: Plugin-Kopf sagt 11.0, aktuell ist 11.2.2. In der Testinstanz läuft
  das Plugin unter 11.2.2 ohne Meldung (Laufzeittest 26.11.2026).
- Aufwand klein, braucht aber eine neue Version. Kein eigenes Release dafür,
  sondern beim nächsten Release mitnehmen.

### 2. Französisch und Italienisch freigeben lassen

- Heute auf translate.wordpress.org (26.11.2026): fr und it je 145 Texte im
  Code und 135 in der readme, **alle «waiting», 0 freigegeben**. Shops in der
  Romandie und im Tessin sehen das Plugin darum englisch, obwohl die
  Übersetzung bereitliegt.
- Entscheid bisher (`docs/uebersetzungen.md`): bewusst **keine** fr-/it-PTE
  beantragt, weil kein Muttersprachler prüft. Die Freigabe soll ein GTE der
  Sprachteams machen. Das ist seit Juli nicht passiert.
- Möglicher nächster Schritt, ohne den Entscheid umzustossen: die Sprachteams
  fr und it (Polyglots) gezielt um Review bitten. Alternative: eine
  französisch- oder italienischsprachige Person aus dem eigenen Umfeld als
  PTE vorschlagen.
- Entscheid offen: Roger.

### 3. Neue Funktionen

- Keine belegten Wünsche. Quellen, aus denen sie kommen könnten: Rückmeldungen
  von Kundinnen, eigene Shops mit dem Plugin, Anfragen über die Landingpage
  `blueforce.ch/twint`, Bewertungen und Forum auf wordpress.org.
- Eintrag hier nur mit Quelle (wer hat was gefragt, wann).

## Nicht geplant

- Anbindung an eine TWINT-API: Es gibt keine öffentliche Schnittstelle für
  P2P-Zahlungen (siehe `docs/WARUM-DIESES-PLUGIN.md`). Das Plugin bleibt
  bewusst manuell.
