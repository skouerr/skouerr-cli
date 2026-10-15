#!/usr/bin/env bash
#
# End-to-end checks of the commands that download code (make:theme, import:block)
# against a fake Skouerr server.
#
# Usage: tests/integration/library.sh /path/to/wordpress

set -uo pipefail

WP_PATH="${1:?Usage: library.sh /path/to/wordpress}"
export WP_CLI_ALLOW_ROOT=1
FAILURES=0
SERVER="http://127.0.0.1:8099"

wp_() { wp --path="$WP_PATH" "$@"; }

check() {
	local label="$1" expected="$2" actual="$3"
	if [[ "$expected" == "$actual" ]]; then
		echo "ok   - $label"
	else
		echo "FAIL - $label: expected $expected, got $actual"
		FAILURES=$((FAILURES + 1))
	fi
}

php -S 127.0.0.1:8099 "$(dirname "$0")/fake-library/router.php" >/dev/null 2>&1 &
SERVER_PID=$!
cleanup() {
	kill "$SERVER_PID" 2>/dev/null
	for constant in SKOUERR_REMOTE_THEME_URL SKOUERR_LIBRARY_TOKENS SKOUERR_ALLOWED_LIBRARIES; do
		wp_ config delete "$constant" --type=constant >/dev/null 2>&1
	done
}
trap cleanup EXIT
sleep 1

THEMES_DIR="$(wp_ eval 'echo get_theme_root();')"
THEME_DIR="$(wp_ eval 'echo get_template_directory();')"
rm -rf "$THEMES_DIR/acme-test" "$THEME_DIR/blocks/promo"

# make:theme: download, verified checksum, extraction, no activation by default.
wp_ config set SKOUERR_REMOTE_THEME_URL "$SERVER/theme" --type=constant >/dev/null
out="$(wp_ skouerr make:theme --title="Acme Test" --name=acme-test --skip-composer --format=json)"
check "make:theme exit code" "0" "$?"
check "theme installed" "yes" "$([[ -f "$THEMES_DIR/acme-test/style.css" && -f "$THEMES_DIR/acme-test/templates/index.html" ]] && echo yes || echo no)"
check "theme title passed to the server" "yes" "$(grep -q 'Theme Name: Acme Test' "$THEMES_DIR/acme-test/style.css" && echo yes || echo no)"
check "checksum verified (no warning)" "0" "$(php -r 'echo count(json_decode(stream_get_contents(STDIN), true)["warnings"]);' <<<"$out")"
check "theme not activated" "no" "$([[ "$(wp_ eval 'echo get_stylesheet();')" == "acme-test" ]] && echo yes || echo no)"
wp_ skouerr make:theme --title="Acme Test" --name=acme-test --skip-composer --format=json >/dev/null
check "existing theme is a conflict" "3" "$?"
wp_ skouerr make:theme --title="Acme Test" --name=acme-test2 --sha256="$(printf '0%.0s' {1..64})" --skip-composer --format=json >/dev/null
check "wrong --sha256 is refused" "4" "$?"
check "nothing installed on checksum error" "no" "$([[ -d "$THEMES_DIR/acme-test2" ]] && echo yes || echo no)"
rm -rf "$THEMES_DIR/acme-test"

# import:block.
out="$(wp_ skouerr import:block --library="$SERVER" --type=blocks --list --format=json)"
check "library listing drops unsafe names" '["promo","evil"]' "$(php -r 'echo json_encode(array_column(json_decode(stream_get_contents(STDIN), true)["data"]["items"], "name"));' <<<"$out")"
wp_ skouerr import:block --library="$SERVER" --type=blocks --name=promo --format=json >/dev/null
check "import:block exit code" "0" "$?"
check "block imported" "yes" "$([[ -f "$THEME_DIR/blocks/promo/promo.block.json" ]] && echo yes || echo no)"
wp_ skouerr import:block --library="$SERVER" --type=blocks --name=evil --format=json >/dev/null
check "zip slip archive refused" "4" "$?"
wp_ skouerr import:block --library="$SERVER" --type=blocks --name=tampered --format=json >/dev/null
check "tampered archive refused" "4" "$?"
wp_ skouerr import:block --library="$SERVER" --type=blocks --name=missing --format=json >/dev/null
check "HTTP 404 reported" "4" "$?"
out="$(wp_ skouerr import:block --library="$SERVER" --type=blocks --format=json)"
check "missing name lists the choices" '["promo","evil"]' "$(php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true)["error"]["details"]["choices"]);' <<<"$out")"
rm -rf "$THEME_DIR/blocks/promo"

# Private library with a token.
wp_ skouerr import:block --library="$SERVER/private" --type=blocks --name=promo --format=json >/dev/null
check "private library without token" "4" "$?"
wp_ config set SKOUERR_LIBRARY_TOKENS "array( '$SERVER/private' => 'secret' )" --raw --type=constant >/dev/null
wp_ skouerr import:block --library="$SERVER/private" --type=blocks --name=promo --format=json >/dev/null
check "private library with token" "0" "$?"
rm -rf "$THEME_DIR/blocks/promo"

# Allowlist.
wp_ config set SKOUERR_ALLOWED_LIBRARIES "https://library.example.com" --type=constant >/dev/null
wp_ skouerr import:block --library="$SERVER" --type=blocks --name=promo --format=json >/dev/null
check "library outside the allowlist refused" "2" "$?"

echo
if [[ $FAILURES -gt 0 ]]; then
	echo "$FAILURES check(s) failed."
	exit 1
fi
echo "All checks passed."
