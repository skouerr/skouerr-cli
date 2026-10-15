#!/usr/bin/env bash
#
# End-to-end checks of the `wp skouerr` commands against a real WordPress.
#
# Usage: tests/integration/smoke.sh /path/to/wordpress
#
# Requirements: WordPress installed, Skouerr CLI loaded as a MU plugin,
# an active block theme that can be modified (it is used as a scratch theme).

set -uo pipefail

WP_PATH="${1:?Usage: smoke.sh /path/to/wordpress}"
export WP_CLI_ALLOW_ROOT=1
FAILURES=0

wp_skouerr() {
	wp --path="$WP_PATH" skouerr "$@"
}

# json_get <json> <php expression on $d>
json_get() {
	php -r '$d = json_decode(stream_get_contents(STDIN), true); if (!is_array($d)) { fwrite(STDERR, "not JSON\n"); exit(1); } echo json_encode(eval("return " . $argv[1] . ";"), JSON_UNESCAPED_SLASHES);' "$2" "${@:3}" <<<"$1"
}

check() {
	local label="$1" expected="$2" actual="$3"
	if [[ "$expected" == "$actual" ]]; then
		echo "ok   - $label"
	else
		echo "FAIL - $label: expected $expected, got $actual"
		FAILURES=$((FAILURES + 1))
	fi
}

THEME_DIR="$(wp --path="$WP_PATH" eval 'echo get_template_directory();')"
rm -rf "$THEME_DIR"/blocks/{hero-banner,team,accordion,editor-demo,card,card-x} "$THEME_DIR/post-types/event" "$THEME_DIR/bindings/year" \
	"$THEME_DIR/js/admin/variations/card.js" "$THEME_DIR/templates/single-event.html" "$THEME_DIR"/patterns/{promo,cta,uses-card}.php \
	"$THEME_DIR/styles/dark.json" "$THEME_DIR/styles/blocks/outline.json" "$THEME_DIR/acf-json" "$THEME_DIR"/parts/navigation-*.html \
	"$THEME_DIR/taxonomies" "$THEME_DIR/.claude" "$THEME_DIR/AGENTS.md" "$THEME_DIR/.skouerr"
# save:styles rewrites theme.json: keep the original.
[[ -f "$THEME_DIR/theme.json.orig" ]] && cp "$THEME_DIR/theme.json.orig" "$THEME_DIR/theme.json" || cp "$THEME_DIR/theme.json" "$THEME_DIR/theme.json.orig"
for type in wp_navigation wp_global_styles; do
	wp --path="$WP_PATH" post list --post_type=$type --post_status=any --field=ID | xargs -r wp --path="$WP_PATH" post delete --force >/dev/null
done
wp --path="$WP_PATH" post list --post_type=wp_block --post_status=any --field=ID | xargs -r wp --path="$WP_PATH" post delete --force >/dev/null

# describe: valid manifest.
out="$(wp_skouerr describe --format=json)"
check "describe is JSON and ok" "true" "$(json_get "$out" '$d["ok"]')"
check "describe lists 22 commands" "22" "$(json_get "$out" 'count($d["data"]["manifest"]["commands"])')"

# info.
out="$(wp_skouerr info --format=json)"
check "info reports the theme directory" "\"$THEME_DIR\"" "$(json_get "$out" '$d["data"]["info"]["theme"]["directory"]')"

# make:block, non-interactive, JSON.
out="$(wp_skouerr make:block --title='Hero "Banner"' --type=native --template=twig --icon=cover-image --format=json)"
check "make:block ok" "true" "$(json_get "$out" '$d["ok"]')"
check "make:block writes 6 files" "6" "$(json_get "$out" 'count($d["files"])')"
check "block.json is valid and escaped" "\"Hero \\\"Banner\\\"\"" "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1]), true)["title"]);' "$THEME_DIR/blocks/hero-banner/hero-banner.block.json")"

# Conflict without --force, exit code 3.
out="$(wp_skouerr make:block --title='Hero Banner' --format=json)"
code=$?
check "make:block conflict exit code" "3" "$code"
check "make:block conflict error code" "\"conflict\"" "$(json_get "$out" '$d["error"]["code"]')"

# Missing parameter in non-interactive mode, exit code 2 with the parameter schema.
out="$(wp_skouerr make:post-type --format=json)"
code=$?
check "missing parameter exit code" "2" "$code"
check "missing parameter is named" "\"slug\"" "$(json_get "$out" '$d["error"]["details"]["parameter"]["name"]')"

# Path traversal is rejected.
wp_skouerr make:template --name=../../../evil --format=json >/dev/null
check "make:template rejects traversal" "2" "$?"

# Code injection in generated PHP is neutralised.
wp_skouerr make:binding --title="x'; echo 'pwned'; //" --name=year --format=json >/dev/null
check "make:binding ok" "0" "$?"
check "binding returns the title as a string" "x'; echo 'pwned'; //" "$(php -r 'echo require $argv[1];' "$THEME_DIR/bindings/year/year.php")"

# make:post-type and the generated file is valid PHP.
wp_skouerr make:post-type --slug=event --label=Event --plural=Events --icon=calendar-alt --format=json >/dev/null
check "make:post-type ok" "0" "$?"
php -l "$THEME_DIR/post-types/event/event.functions.php" >/dev/null
check "post type file is valid PHP" "0" "$?"

# make:variation.
wp_skouerr make:variation --block=core/group --title="Card" --format=json >/dev/null
check "make:variation ok" "0" "$?"

# Dry run writes nothing.
wp_skouerr make:template --name=single-event --dry-run --format=json >/dev/null
check "dry-run exit code" "0" "$?"
check "dry-run writes nothing" "no" "$([[ -f "$THEME_DIR/templates/single-event.html" ]] && echo yes || echo no)"

# list:blocks.
out="$(wp_skouerr list:blocks --format=json)"
check "list:blocks finds the block" "true" "$(json_get "$out" 'in_array("skouerr/hero-banner", array_column($d["data"]["blocks"], "name"), true)')"

# save:pattern: a malicious title cannot inject PHP.
wp --path="$WP_PATH" post create --post_type=wp_block --post_status=publish --post_name=promo \
	--post_title='Promo */ echo "pwned"; /*' --post_content='<!-- wp:paragraph --><p>Hi <?php echo "pwned"; ?></p><!-- /wp:paragraph -->' >/dev/null
out="$(wp_skouerr save:pattern --pattern=promo --delete --format=json)"
check "save:pattern ok" "true" "$(json_get "$out" '$d["ok"]')"
rendered="$(php "$THEME_DIR/patterns/promo.php")"
check "pattern header executes nothing" "<!-- wp:paragraph -->" "${rendered:0:21}"
check "pattern PHP tag is rendered as text" "yes" "$(grep -qF 'Hi &lt;?php echo "pwned"; ?>' <<<"$rendered" && echo yes || echo no)"
trashed_id="$(json_get "$out" '$d["data"]["trashed"][0]')"
check "pattern moved to trash" "trash" "$(wp --path="$WP_PATH" post get "$trashed_id" --field=post_status)"

# ACF block with fields: field group in acf-json with stable keys.
out="$(wp_skouerr make:block --title=Team --type=acf --template=php --fields='[{"name":"heading","type":"text"},{"name":"members","type":"repeater","sub_fields":[{"name":"photo","type":"image"}]}]' --format=json)"
check "make:block acf with fields ok" "true" "$(json_get "$out" '$d["ok"]')"
group="$(ls "$THEME_DIR"/acf-json/group_*.json | head -1)"
check "field group targets the block" "skouerr/team" "$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["location"][0][0]["value"];' "$group")"
out="$(wp_skouerr make:block --title=Wrong --type=native --fields='[{"name":"a","type":"text"}]' --format=json)"
check "fields refused on native blocks" "2" "$?"

# Interactivity API block with attributes.
wp_skouerr make:block --title=Accordion --type=interactive --template=php --attributes='{"open":{"type":"boolean","default":false}}' --format=json >/dev/null
check "make:block interactive ok" "0" "$?"
check "interactive block registers its view module" "yes" "$(wp --path="$WP_PATH" eval 'register_block_type_from_metadata( get_template_directory() . "/blocks/accordion/accordion.block.json" ); $b = WP_Block_Type_Registry::get_instance()->get_registered("skouerr/accordion"); echo ( $b && $b->view_script_module_ids && $b->supports["interactivity"] && isset( $b->attributes["open"] ) ) ? "yes" : "no";')"

# React block.
out="$(wp_skouerr make:block --title="Editor demo" --type=native --template=react --format=json)"
check "make:block react ok" "true" "$(json_get "$out" '$d["ok"]')"
check "react block has a JSX editor" "yes" "$([[ -f "$THEME_DIR/blocks/editor-demo/src/editor-demo.editor.js" ]] && echo yes || echo no)"

# Unknown combination lists the available kinds.
out="$(wp_skouerr make:block --title=X --type=interactive --template=react --format=json)"
check "unknown kind exit code" "2" "$?"
check "unknown kind lists choices" "true" "$(json_get "$out" 'in_array("interactive-php", $d["error"]["details"]["choices"], true)')"

# Theme override of a template.
mkdir -p "$THEME_DIR/.skouerr/templates/binding"
printf '<?php\n// Custom binding %%SK_BINDING_NAME%%\nreturn "custom";\n' > "$THEME_DIR/.skouerr/templates/binding/default"
wp_skouerr make:binding --title=Year --name=year --force --format=json >/dev/null
check "theme template override is used" "custom" "$(php -r 'echo require $argv[1];' "$THEME_DIR/bindings/year/year.php")"
rm -rf "$THEME_DIR/.skouerr"

# Patch format writes nothing and applies with git.
patch="$(wp_skouerr make:block --title=Card --format=patch)"
check "patch mode writes nothing" "no" "$([[ -d "$THEME_DIR/blocks/card" ]] && echo yes || echo no)"
(cd "$THEME_DIR" && git apply --check --whitespace=nowarn - <<<"$patch")
check "patch applies with git" "0" "$?"

# make:pattern, make:style-variation, make:block-style, make:taxonomy.
wp_skouerr make:pattern --title="CTA" --categories=featured,call-to-action --keywords="cta,button" --format=json >/dev/null
check "make:pattern ok" "0" "$?"
check "pattern is registered by WordPress" "yes" "$(wp --path="$WP_PATH" eval 'echo WP_Block_Patterns_Registry::get_instance()->is_registered("skouerr/cta") ? "yes" : "no";')"
wp_skouerr make:style-variation --title=Dark --format=json >/dev/null
check "make:style-variation ok" "0" "$?"
wp_skouerr make:block-style --title=Outline --block-types=core/button,core/group --format=json >/dev/null
check "make:block-style ok" "0" "$?"
check "block style is registered by WordPress" "yes" "$(wp --path="$WP_PATH" eval 'echo in_array( "outline", array_keys( WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( "core/button" ) ), true ) || array_filter( WP_Theme_JSON_Resolver::get_style_variations( "block" ), fn( $v ) => "outline" === ( $v["slug"] ?? "" ) ) ? "yes" : "no";')"
wp_skouerr make:taxonomy --slug=event-type --label="Event type" --post-types=post --hierarchical --format=json >/dev/null
check "make:taxonomy ok" "0" "$?"
php -l "$THEME_DIR/taxonomies/event-type/event-type.functions.php" >/dev/null
check "taxonomy file is valid PHP" "0" "$?"

# rename:block, theme markup and database content included.
wp_skouerr make:block --title=Card --format=json >/dev/null
printf '<?php\n/**\n * Title: Uses card\n * Slug: skouerr/uses-card\n */\n?>\n<!-- wp:skouerr/card /-->\n' > "$THEME_DIR/patterns/uses-card.php"
post_id="$(wp --path="$WP_PATH" post create --post_status=publish --post_title=Card --post_content='<!-- wp:skouerr/card /-->' --porcelain)"
out="$(wp_skouerr rename:block --from=card --to=card-x --update-content --format=json)"
check "rename:block ok" "true" "$(json_get "$out" '$d["ok"]')"
check "renamed block.json" "skouerr/card-x" "$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["name"];' "$THEME_DIR/blocks/card-x/card-x.block.json")"
check "old folder removed" "no" "$([[ -d "$THEME_DIR/blocks/card" ]] && echo yes || echo no)"
check "theme markup renamed" "yes" "$(grep -q 'wp:skouerr/card-x' "$THEME_DIR/patterns/uses-card.php" && echo yes || echo no)"
check "post content renamed" "<!-- wp:skouerr/card-x /-->" "$(wp --path="$WP_PATH" post get "$post_id" --field=post_content)"

# delete:block needs --yes without a terminal.
wp_skouerr delete:block --name=card-x --format=json >/dev/null
check "delete:block without --yes is aborted" "6" "$?"
out="$(wp_skouerr delete:block --name=card-x --yes --format=json)"
check "delete:block reports usage" "true" "$(json_get "$out" 'in_array((int) $argv[2], $d["data"]["usage"]["posts"], true)' "$post_id")"
check "block folder deleted" "no" "$([[ -d "$THEME_DIR/blocks/card-x" ]] && echo yes || echo no)"

# save:styles merges the user global styles into theme.json.
wp --path="$WP_PATH" --user=1 eval '$theme = wp_get_theme(); $post = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( $theme, true ); wp_update_post( array( "ID" => $post["ID"], "post_content" => wp_json_encode( array( "version" => 3, "isGlobalStylesUserThemeJSON" => true, "styles" => array( "color" => array( "background" => "#123456" ) ) ) ) ) );' >/dev/null
out="$(wp_skouerr save:styles --delete --format=json)"
check "save:styles ok" "true" "$(json_get "$out" '$d["ok"]')"
check "theme.json updated" "#123456" "$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["styles"]["color"]["background"];' "$THEME_DIR/theme.json")"
check "theme.json keeps its settings" "true" "$(php -r 'echo json_encode(!empty(json_decode(file_get_contents($argv[1]), true)["settings"]["color"]["palette"]));' "$THEME_DIR/theme.json")"

# save:navigation.
wp --path="$WP_PATH" post create --post_type=wp_navigation --post_status=publish --post_name=main-menu --post_title="Main menu" --post_content='<!-- wp:navigation-link {"label":"Home","url":"/"} /-->' >/dev/null
out="$(wp_skouerr save:navigation --navigation=main-menu --format=json)"
check "save:navigation ok" "true" "$(json_get "$out" '$d["ok"]')"
check "navigation part written" "yes" "$(grep -q 'wp:navigation-link' "$THEME_DIR/parts/navigation-main-menu.html" && echo yes || echo no)"

# agent:install.
wp_skouerr agent:install --format=json >/dev/null
check "agent:install writes the skill" "yes" "$([[ -f "$THEME_DIR/.claude/skills/skouerr-cli/SKILL.md" && -f "$THEME_DIR/AGENTS.md" ]] && echo yes || echo no)"

# Abilities API: same commands for REST/MCP clients, with permissions.
check "abilities are registered" "yes" "$(wp --path="$WP_PATH" eval 'echo wp_get_ability( "skouerr/make-block" ) && wp_get_ability( "skouerr/info" ) && ! wp_get_ability( "skouerr/make-theme" ) ? "yes" : "no";')"
check "abilities need a capable user" "no" "$(wp --path="$WP_PATH" eval '$r = wp_get_ability( "skouerr/make-template" )->execute( array( "name" => "ability-test" ) ); echo is_wp_error( $r ) ? "no" : "yes";')"
check "ability runs the command" "true" "$(wp --path="$WP_PATH" --user=1 eval '$r = wp_get_ability( "skouerr/make-template" )->execute( array( "name" => "ability-test", "dry-run" => true ) ); echo is_wp_error( $r ) ? $r->get_error_message() : json_encode( $r["ok"] );')"
check "ability errors are WP_Error" "skouerr_invalid_input" "$(wp --path="$WP_PATH" --user=1 eval '$r = wp_get_ability( "skouerr/make-template" )->execute( array( "name" => "../x" ) ); echo is_wp_error( $r ) ? $r->get_error_code() : "ok";')"

echo
if [[ $FAILURES -gt 0 ]]; then
	echo "$FAILURES check(s) failed."
	exit 1
fi
echo "All checks passed."
