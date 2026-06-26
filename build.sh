#!/usr/bin/env bash
#
# Baut ein sauberes, installierbares Plugin-ZIP zum Testen / Hochladen.
#
# Das ZIP enthält genau einen Ordner «blueforce-manual-payments-for-twint/» mit allen
# Plugin-Dateien (ausser den in .gitattributes als export-ignore markierten). Praktisch
# zum lokalen Installieren und zum Befüllen des WordPress.org-SVN-Trunks.
#
# Verwendung:
#   ./build.sh            # ZIP aus dem aktuellen HEAD
#   ./build.sh 1.4.0      # ZIP aus einem bestimmten Tag/Commit
#
set -euo pipefail

SLUG="blueforce-manual-payments-for-twint"
REF="${1:-HEAD}"
OUT="${SLUG}.zip"

cd "$(dirname "$0")"
rm -f "$OUT"

git archive --format=zip --prefix="${SLUG}/" -o "$OUT" "$REF"

echo "Erzeugt: $OUT  (Ref: $REF)"

# Inhaltsliste einmal erzeugen (robuste Prüfung ohne grep -q / SIGPIPE).
LIST="$(unzip -l "$OUT")"
echo "--- Inhalt (Auszug) ---"
printf '%s\n' "$LIST" | sed -n '1,12p'
echo "..."

require_in_zip() {
	local needle="$1" label="$2"
	if printf '%s\n' "$LIST" | grep -Fq -- "$needle"; then
		echo "  OK   $label"
	else
		echo "  FEHLT: $label  ($needle)"
		exit 1
	fi
}

echo "Pflicht-Bestandteile:"
require_in_zip "${SLUG}/${SLUG}.php"                           "Hauptdatei"
require_in_zip "${SLUG}/readme.txt"                            "readme.txt (WordPress.org)"
require_in_zip "${SLUG}/includes/class-bf-twint-gateway.php" "Gateway-Klasse"
echo "ZIP ist vollständig."
