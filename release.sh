#!/usr/bin/env bash
#
# Release-Prozess für das TWINT-Plugin: vom Repo bis auf wordpress.org.
#
# Ausgeliefert wird ausschliesslich über das WordPress-SVN. GitHub ist nur
# Spiegel: direkt auf main committen und pushen, keine Pull Requests.
#
# Verwendung:
#   ./release.sh 1.6.4              # Vollrelease: trunk + neuer Tag + Stable tag
#   ./release.sh --readme-only      # nur readme.txt nachziehen, ohne Versionssprung
#   ./release.sh 1.6.4 --dry-run    # alles prüfen und bauen, nichts veröffentlichen
#
# Warum readme-only ein eigener Modus ist: Die Plugin-Seite liest die readme
# ausschliesslich aus dem Verzeichnis, auf das «Stable tag» zeigt. Nur trunk zu
# ändern bewirkt auf der Seite gar nichts. Der Modus committet darum trunk UND
# den aktuellen Tag, ohne neue Version.
#
set -euo pipefail

SLUG="blueforce-manual-payments-for-twint"
SVN_DIR="${HOME}/Documents/Websites/plg-twint-svn"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
SVN_BIN="/opt/homebrew/opt/subversion/bin/svn"
# PHP ist auf diesem Mac nicht im PATH (MAMP). Composer-Skripte brauchen es dort.
PHP_BIN_DIR="/Applications/MAMP/bin/php/php8.3.30/bin"
COMPOSER="/Applications/MAMP/bin/php/composer"

cd "$(dirname "$0")"
REPO="$(pwd)"

VERSION=""
MODE="release"
DRY_RUN=0

for arg in "$@"; do
	case "$arg" in
		--readme-only) MODE="readme" ;;
		--dry-run)     DRY_RUN=1 ;;
		--*)           echo "Unbekannte Option: $arg" >&2; exit 1 ;;
		*)             VERSION="$arg" ;;
	esac
done

say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32mOK\033[0m   %s\n' "$*"; }
fail() { printf '  \033[31mFEHLER\033[0m %s\n' "$*" >&2; exit 1; }
warn() { printf '  \033[33mACHTUNG\033[0m %s\n' "$*"; }

# ─────────────────────────────────────────────────────────────────────────────
say "1/6  Vorbedingungen"

[ -x "$SVN_BIN" ]      || fail "svn nicht gefunden: $SVN_BIN"
[ -d "$SVN_DIR/.svn" ] || fail "SVN-Checkout fehlt: $SVN_DIR"
[ -d "$PHP_BIN_DIR" ]  || fail "MAMP-PHP nicht gefunden: $PHP_BIN_DIR"
export PATH="$PHP_BIN_DIR:$PATH"

if [ -n "$(git status --porcelain)" ]; then
	git status --short
	fail "Arbeitsverzeichnis nicht sauber. build.sh baut aus 'git archive HEAD' und sieht nur Committetes."
fi
ok "Arbeitsverzeichnis sauber"

if [ "$MODE" = "release" ]; then
	[ -n "$VERSION" ] || fail "Version fehlt. Aufruf: ./release.sh 1.6.4"
	echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$' || fail "Version muss MAJOR.MINOR.PATCH sein: $VERSION"

	# Die Version steht an drei Stellen. Läuft eine davon auseinander, installiert
	# WordPress etwas anderes als die Plugin-Seite anzeigt.
	hdr=$(grep -m1 '^ \* Version:' "$SLUG.php" | sed 's/.*Version: *//' | tr -d ' ')
	def=$(grep -m1 "define( 'BF_TWINT_VERSION'" "$SLUG.php" | sed "s/.*'\([0-9.]*\)'.*/\1/")
	stb=$(grep -m1 '^Stable tag:' readme.txt | sed 's/Stable tag: *//' | tr -d ' ')
	for pair in "Plugin-Header:$hdr" "BF_TWINT_VERSION:$def" "Stable tag:$stb"; do
		name="${pair%%:*}"; val="${pair#*:}"
		[ "$val" = "$VERSION" ] || fail "$name steht auf '$val', erwartet '$VERSION'"
	done
	ok "Version $VERSION an allen drei Stellen"

	grep -q "^= ${VERSION} =$" readme.txt      || fail "Changelog-Eintrag '= $VERSION =' fehlt in readme.txt"
	grep -q "^## \[${VERSION}\]" CHANGELOG.md  || fail "Changelog-Eintrag '## [$VERSION]' fehlt in CHANGELOG.md"
	ok "Changelog in readme.txt und CHANGELOG.md"

	"$SVN_BIN" ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1 && fail "Tag $VERSION existiert bereits im SVN"
	ok "Tag $VERSION ist frei"
fi

# ─────────────────────────────────────────────────────────────────────────────
say "2/6  Qualität"

find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -not -path './testinstanz/*' \
	-exec php -l {} \; | grep -v '^No syntax errors' && fail "PHP-Syntaxfehler" || ok "PHP-Syntax"

"$COMPOSER" phpcs >/dev/null 2>&1 || fail "PHPCS meldet Verstösse. Details: composer phpcs"
ok "PHPCS"

# ─────────────────────────────────────────────────────────────────────────────
say "3/6  ZIP bauen"

./build.sh >/dev/null || fail "build.sh fehlgeschlagen"
if [ "$MODE" = "release" ]; then
	zipver=$(unzip -p "$SLUG.zip" "$SLUG/$SLUG.php" | grep -m1 '^ \* Version:' | sed 's/.*Version: *//' | tr -d ' \r')
	[ "$zipver" = "$VERSION" ] || fail "ZIP enthält Version '$zipver' statt '$VERSION'. Committet?"
	ok "ZIP gebaut und trägt $VERSION"
else
	ok "ZIP gebaut"
fi

# ─────────────────────────────────────────────────────────────────────────────
say "4/6  SVN vorbereiten"

# Zwingend: Der Checkout hing schon einmal Revisionen zurück und kannte den
# aktuellen Tag gar nicht. Ohne update arbeitet man auf einem Geisterstand.
"$SVN_BIN" update -q "$SVN_DIR" || fail "svn update fehlgeschlagen"
ok "Checkout aktuell (r$("$SVN_BIN" info "$SVN_DIR" | awk '/^Revision/{print $2}'))"

STABLE=$(grep -m1 '^Stable tag:' readme.txt | sed 's/Stable tag: *//' | tr -d ' ')

if [ "$MODE" = "readme" ]; then
	[ -d "$SVN_DIR/tags/$STABLE" ] || fail "tags/$STABLE fehlt im Checkout"
	cp readme.txt "$SVN_DIR/trunk/readme.txt"
	cp readme.txt "$SVN_DIR/tags/$STABLE/readme.txt"
	TARGETS=("$SVN_DIR/trunk/readme.txt" "$SVN_DIR/tags/$STABLE/readme.txt")
	MSG="readme nachgezogen (trunk + tag $STABLE, keine Codeaenderung)"
	ok "readme.txt in trunk und tags/$STABLE gelegt"
else
	TMP=$(mktemp -d)
	trap 'rm -rf "$TMP"' EXIT
	unzip -q "$SLUG.zip" -d "$TMP"
	rsync -a --delete --exclude=".svn" "$TMP/$SLUG/" "$SVN_DIR/trunk/"
	TARGETS=("$SVN_DIR/trunk")
	MSG="$VERSION"
	ok "ZIP-Inhalt nach trunk gespiegelt"
fi

# Neue und gelöschte Dateien anmelden, sonst fehlen sie im Release.
cd "$SVN_DIR"
"$SVN_BIN" status trunk | awk '/^\?/{print $2}' | while read -r f; do "$SVN_BIN" add "$f" >/dev/null; done
"$SVN_BIN" status trunk | awk '/^!/{print $2}' | while read -r f; do "$SVN_BIN" delete "$f" >/dev/null; done

say "Diese Änderungen gehen raus:"
"$SVN_BIN" status trunk "${TARGETS[@]}" 2>/dev/null | sort -u || true
if [ -z "$("$SVN_BIN" status trunk)" ] && [ "$MODE" = "release" ]; then
	warn "Keine Änderungen im trunk. Nichts zu tun."
	exit 0
fi

if [ "$DRY_RUN" = "1" ]; then
	say "Dry-Run: hier ist Schluss, nichts veröffentlicht."
	exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
say "5/6  Veröffentlichen"

"$SVN_BIN" commit -m "$MSG" "${TARGETS[@]}" || fail "svn commit fehlgeschlagen"
ok "committet"

if [ "$MODE" = "release" ]; then
	"$SVN_BIN" copy "$SVN_URL/trunk" "$SVN_URL/tags/$VERSION" -m "Tagging $VERSION" || fail "Tag anlegen fehlgeschlagen"
	ok "Tag $VERSION angelegt"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Pflicht, analog zum Post-Deploy-200-Check der Websites: Ein Release gilt erst
# als erfolgreich, wenn der Server bestätigt, was wir glauben hochgeladen zu haben.
say "6/6  Gegen den Server verifizieren"

CHECK_TAG="${VERSION:-$STABLE}"
tagver=$("$SVN_BIN" cat "$SVN_URL/tags/$CHECK_TAG/$SLUG.php" 2>/dev/null | grep -m1 '^ \* Version:' | sed 's/.*Version: *//' | tr -d ' \r')
tagstb=$("$SVN_BIN" cat "$SVN_URL/tags/$CHECK_TAG/readme.txt" 2>/dev/null | grep -m1 '^Stable tag:' | sed 's/Stable tag: *//' | tr -d ' \r')
[ "$tagver" = "$CHECK_TAG" ] || fail "tags/$CHECK_TAG trägt Version '$tagver'"
[ "$tagstb" = "$CHECK_TAG" ] || fail "tags/$CHECK_TAG hat Stable tag '$tagstb'"
ok "tags/$CHECK_TAG serverseitig korrekt"

# Die Plugin-Seite hängt hinter einem Cache, darum mehrere Versuche.
for i in 1 2 3 4 5 6; do
	live=$(curl -s "https://api.wordpress.org/plugins/info/1.0/$SLUG.json" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("version",""))' 2>/dev/null || echo "")
	[ "$live" = "$CHECK_TAG" ] && { ok "wordpress.org liefert $live aus"; break; }
	[ "$i" = "6" ] && warn "wordpress.org zeigt noch '$live' statt '$CHECK_TAG'. Cache, in ein paar Minuten nachschauen."
	sleep 20
done

# ─────────────────────────────────────────────────────────────────────────────
say "Fertig. Was jetzt noch von Hand kommt:"
cat <<EOF

  1. GitHub-Spiegel:   git push origin main
  2. Übersetzungen:    Neue Changelog-Strings brauchen de (du + Sie), fr und it.
                       ACHTUNG: Erst liefern, wenn GlotPress das Release eingelesen
                       hat, das dauert Stunden. Prüfen mit:

                       curl -s "https://translate.wordpress.org/projects/wp-plugins/$SLUG/stable-readme/de/default/export-translations/?format=po" | grep -c "<neuer String>"

                       Erst wenn das >0 liefert, die kompletten .po je Zweig bauen.
                       Regeln stehen in docs/uebersetzungen.md.

EOF
