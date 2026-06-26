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

forbid_in_zip() {
	local needle="$1" label="$2"
	if printf '%s\n' "$LIST" | grep -Fq -- "$needle"; then
		echo "  UNERWÜNSCHT: $label liegt im ZIP  ($needle)"
		exit 1
	else
		echo "  OK   $label nicht im ZIP"
	fi
}

echo "Pflicht-Bestandteile:"
require_in_zip "${SLUG}/${SLUG}.php"                         "Hauptdatei"
require_in_zip "${SLUG}/readme.txt"                          "readme.txt (WordPress.org)"
require_in_zip "${SLUG}/includes/class-bf-twint-gateway.php" "Gateway-Klasse"
require_in_zip "${SLUG}/uninstall.php"                       "uninstall.php (Deinstallations-Cleanup)"
require_in_zip "${SLUG}/composer.json"                       "composer.json (Dev-Tooling, von WordPress.org gewünscht)"

echo "Nicht ins ZIP (GitHub-Doku mit Repo-internen Links):"
forbid_in_zip "${SLUG}/README.md"       "README.md"
forbid_in_zip "${SLUG}/CHANGELOG.md"    "CHANGELOG.md"
forbid_in_zip "${SLUG}/CONTRIBUTING.md" "CONTRIBUTING.md"

echo "Nicht ins ZIP (Entwicklung, Tests, Repo- und Verzeichnis-Assets):"
forbid_in_zip "${SLUG}/.github/"        ".github"
forbid_in_zip "${SLUG}/.gitattributes"  ".gitattributes"
forbid_in_zip "${SLUG}/.gitignore"      ".gitignore"
forbid_in_zip "${SLUG}/_marketing/"     "_marketing"
forbid_in_zip "${SLUG}/basic/"          "basic"
forbid_in_zip "${SLUG}/build.sh"        "build.sh"
forbid_in_zip "${SLUG}/composer.lock"   "composer.lock"
forbid_in_zip "${SLUG}/docs/"           "docs"
forbid_in_zip "${SLUG}/node_modules/"   "node_modules"
forbid_in_zip "${SLUG}/phpcs.xml.dist"  "phpcs.xml.dist"
forbid_in_zip "${SLUG}/testinstanz/"    "testinstanz"
forbid_in_zip "${SLUG}/vendor/"         "vendor"
forbid_in_zip "${SLUG}/wordpress.org/"  "wordpress.org"
echo "ZIP ist vollständig."
