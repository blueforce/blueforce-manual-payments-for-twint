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
#   ./release.sh --assets-only      # nur Banner, Icons, Screenshots, Blueprint nachziehen
#   ./release.sh 1.6.4 --dry-run    # alles prüfen und bauen, nichts veröffentlichen
#
# Die Verzeichnis-Assets aus wordpress.org/ (Banner, Icons, Screenshots,
# Blueprint) werden bei JEDEM Lauf nach assets/ gespiegelt, in jedem Modus.
# SVN überträgt nur, was sich wirklich geändert hat. Ein neues Icon geht also
# mit dem nächsten Lauf automatisch raus; ist nur ein Bild neu, reicht
# --assets-only.
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
		--assets-only) MODE="assets" ;;
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

# fr_CH und it_CH sind keine WordPress-Locales. Tauchen sie irgendwo auf,
# ist das ein Fehler und kein Release. Romandie laeuft auf fr_FR, das Tessin
# auf it_IT, beide kommen ueber translate.wordpress.org.
phantom=$(grep -rIn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=testinstanz \
	--exclude-dir=.git --exclude=release.sh -e 'fr_CH' -e 'it_CH' . | grep -v '^./docs/uebersetzungen.md:' || true)
if [ -n "$phantom" ]; then
	printf '%s\n' "$phantom" >&2
	fail "fr_CH/it_CH gefunden. Diese Locales gibt es nicht. Korrigieren auf fr_FR/it_IT."
fi
ok "Keine Phantom-Locales (fr_CH/it_CH)"

# Verzeichnis-Assets: Banner, Icons und Screenshots liegen im SVN neben trunk und
# tags, nicht im Plugin-ZIP. Falsche Masse nimmt wordpress.org stillschweigend
# nicht an, darum hier messen. Die Screenshot-Nummern müssen zur Liste in der
# readme passen, sonst zeigt die Plugin-Seite leere Bilder. (Wie bei Spam
# Protect und Age Check; bei TWINT liegen die Screenshots in einem Unterordner.)
ASSET_DIR="$REPO/wordpress.org"
for pair in "banner-1544x500.png:1544x500" "banner-772x250.png:772x250" "icon-256x256.png:256x256" "icon-128x128.png:128x128"; do
	name="${pair%%:*}"; want="${pair#*:}"
	[ -f "$ASSET_DIR/$name" ] || fail "Asset fehlt: wordpress.org/$name"
	got=$(sips -g pixelWidth -g pixelHeight "$ASSET_DIR/$name" 2>/dev/null | awk '/pixelWidth/{w=$2} /pixelHeight/{h=$2} END{print w"x"h}')
	[ "$got" = "$want" ] || fail "wordpress.org/$name ist $got, erwartet $want"
done
shots=$(awk '/^== Screenshots ==/{f=1;next} /^== /{f=0} f && /^[0-9]+\./{n++} END{print n+0}' readme.txt)
i=1
while [ "$i" -le "$shots" ]; do
	[ -f "$ASSET_DIR/screenshots/screenshot-$i.png" ] || fail "readme nennt Screenshot $i, wordpress.org/screenshots/screenshot-$i.png fehlt"
	i=$(( i + 1 ))
done
files=$(ls "$ASSET_DIR"/screenshots/screenshot-*.png 2>/dev/null | wc -l | tr -d ' ')
[ "$files" = "$shots" ] || fail "$files Screenshot-Dateien, aber $shots in der readme beschrieben"
ok "Verzeichnis-Assets vollständig (2 Banner, 2 Icons, $shots Screenshots)"

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
elif [ "$MODE" = "assets" ]; then
	TARGETS=()
	MSG="Verzeichnis-Assets nachgezogen (keine Codeaenderung)"
else
	TMP=$(mktemp -d)
	trap 'rm -rf "$TMP"' EXIT
	unzip -q "$SLUG.zip" -d "$TMP"
	rsync -a --delete --exclude=".svn" "$TMP/$SLUG/" "$SVN_DIR/trunk/"
	TARGETS=("$SVN_DIR/trunk")
	MSG="$VERSION"
	ok "ZIP-Inhalt nach trunk gespiegelt"
fi

# Die Verzeichnis-Assets hängen an keiner Version: Die Plugin-Seite zeigt immer,
# was gerade in assets/ liegt. Darum bei jedem Lauf mitspiegeln, in jedem Modus.
# Vorfall 29.10.2026: Neues Icon und neuer Banner lagen in wordpress.org/, das
# Skript kannte diesen Schritt nicht, 1.7.0 ging mit den alten Bildern raus.
# Nie den Ordner «wordpress.org/alte versionen/» mitnehmen (Banner mit
# WooCommerce-Logo), darum nur diese Muster.
mkdir -p "$SVN_DIR/assets/blueprints"
for f in "$ASSET_DIR"/banner-*.png "$ASSET_DIR"/icon-*.png "$ASSET_DIR"/icon.svg "$ASSET_DIR"/screenshots/screenshot-*.png; do
	[ -e "$f" ] || continue
	cp "$f" "$SVN_DIR/assets/"
done
if [ -f "$ASSET_DIR/blueprints/blueprint.json" ]; then
	cp "$ASSET_DIR/blueprints/blueprint.json" "$SVN_DIR/assets/blueprints/"
fi
TARGETS+=("$SVN_DIR/assets")
ok "Banner, Icons, Screenshots und Blueprint nach assets/ gelegt"

# Neue und gelöschte Dateien anmelden, sonst fehlen sie im Release.
cd "$SVN_DIR"
"$SVN_BIN" status trunk assets | awk '/^\?/{print $2}' | while read -r f; do "$SVN_BIN" add "$f" >/dev/null; done
"$SVN_BIN" status trunk assets | awk '/^!/{print $2}' | while read -r f; do "$SVN_BIN" delete "$f" >/dev/null; done

# Ohne den Typ meldet SVN neue Bilder als application/octet-stream an, und die
# Plugin-Seite bietet sie zum Herunterladen an, statt sie zu zeigen.
for f in assets/*.png; do
	[ -e "$f" ] || continue
	[ "$("$SVN_BIN" propget svn:mime-type "$f" 2>/dev/null)" = "image/png" ] || "$SVN_BIN" propset -q svn:mime-type image/png "$f"
done
for f in assets/*.svg; do
	[ -e "$f" ] || continue
	[ "$("$SVN_BIN" propget svn:mime-type "$f" 2>/dev/null)" = "image/svg+xml" ] || "$SVN_BIN" propset -q svn:mime-type image/svg+xml "$f"
done
ok "Bildtypen für alle Assets gesetzt"

say "Diese Änderungen gehen raus:"
"$SVN_BIN" status trunk "${TARGETS[@]}" 2>/dev/null | sort -u || true
if [ -z "$("$SVN_BIN" status trunk)" ] && [ "$MODE" = "release" ]; then
	warn "Keine Änderungen im trunk. Nichts zu tun."
	exit 0
fi
if [ "$MODE" = "assets" ] && [ -z "$("$SVN_BIN" status assets)" ]; then
	ok "Assets im SVN sind bereits aktuell. Nichts zu tun."
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

# Assets: Was auf dem Server liegt, muss Byte für Byte der lokalen Datei gleichen.
for name in banner-1544x500.png banner-772x250.png icon-256x256.png icon-128x128.png icon.svg; do
	[ -f "$ASSET_DIR/$name" ] || continue
	srv=$("$SVN_BIN" cat "$SVN_URL/assets/$name" 2>/dev/null | md5 -q || true)
	[ "$srv" = "$(md5 -q "$ASSET_DIR/$name")" ] || fail "assets/$name auf dem Server weicht von wordpress.org/$name ab"
done
ok "Banner und Icons serverseitig aktuell"

if [ "$MODE" = "assets" ]; then
	say "Fertig. Die Plugin-Seite zeigt die neuen Bilder nach wenigen Minuten."
	exit 0
fi

CHECK_TAG="${VERSION:-$STABLE}"
# Erst komplett einlesen, dann filtern: «grep -m1» direkt hinter «svn cat»
# schliesst die Pipe früh, svn bekommt SIGPIPE, und pipefail + set -e beenden
# das Skript stumm, bevor fail() überhaupt etwas melden kann.
tagphp=$("$SVN_BIN" cat "$SVN_URL/tags/$CHECK_TAG/$SLUG.php" 2>/dev/null || true)
tagrdm=$("$SVN_BIN" cat "$SVN_URL/tags/$CHECK_TAG/readme.txt" 2>/dev/null || true)
tagver=$(printf '%s\n' "$tagphp" | grep -m1 '^ \* Version:' | sed 's/.*Version: *//' | tr -d ' \r' || true)
tagstb=$(printf '%s\n' "$tagrdm" | grep -m1 '^Stable tag:' | sed 's/Stable tag: *//' | tr -d ' \r' || true)
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
