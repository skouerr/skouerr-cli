---
name: skouerr-cli
description: Scaffold and maintain a Skouerr FSE WordPress theme with the `wp skouerr` WP-CLI commands — create blocks (native, ACF with fields, Interactivity API; PHP, Twig or React editor), post types, taxonomies, templates, template parts, patterns, style variations, block styles, block variations, block bindings; rename or delete blocks; import items from a Skouerr library; export Site Editor templates, patterns, global styles and navigation menus to theme files. Use whenever the user asks to add or generate any of these in a Skouerr theme, even without naming the CLI.
---

# Skouerr CLI

Use the `wp skouerr` commands instead of writing scaffold files by hand: they follow the theme conventions and validate names.

## Before anything

```bash
wp skouerr info --format=json        # theme directory, existing blocks, post types, ACF/Timber availability
wp skouerr describe <command>        # parameters (enum, pattern, default) of a command
```

If `wp` needs a path or a container (DDEV, Lando, Docker), use the project's usual prefix, e.g. `ddev wp skouerr …`.

## Rules

1. Always pass `--format=json` and read `ok`, `files`, `warnings`, `next_steps`.
2. Preview with `--dry-run`, or `--format=patch` to show the user a diff, then run for real.
3. Exit code 2 (`invalid_input`): fix the parameter named in `error.details`. Choices, if any, are in `error.details.choices`.
4. Exit code 3 (`conflict`): the file exists. Ask the user before re-running with `--force`.
5. `save:template` / `save:pattern`: pass `--delete` only if the user wants the database copy moved to the trash.
6. Run the `next_steps` that make sense (asset build, `wp rewrite flush`).

## Common tasks

```bash
# Block: native or acf, php or twig.
wp skouerr make:block --title="Hero Banner" --type=native --template=twig --icon=cover-image --format=json

# Custom post type.
wp skouerr make:post-type --slug=event --label=Event --plural=Events --icon=calendar-alt --format=json

# Template or template part.
wp skouerr make:template --name=single-event --format=json
wp skouerr make:template --name=footer-minimal --part --format=json

# Block variation and block binding.
wp skouerr make:variation --block=core/group --title="Card" --format=json
wp skouerr make:binding --title="Current year" --format=json

# ACF block with its field group, interactive block with attributes.
wp skouerr make:block --title="Team" --type=acf --fields='[{"name":"heading","type":"text"},{"name":"members","type":"repeater","sub_fields":[{"name":"photo","type":"image"}]}]' --format=json
wp skouerr make:block --title="Accordion" --type=interactive --attributes='{"open":{"type":"boolean","default":false}}' --format=json

# Taxonomy, pattern, styles.
wp skouerr make:taxonomy --slug=event-type --label="Event type" --post-types=event --format=json
wp skouerr make:pattern --title="Hero home" --categories=featured --content='<!-- wp:cover --><div class="wp-block-cover"></div><!-- /wp:cover -->' --format=json
wp skouerr make:block-style --title="Outline" --block-types=core/button --format=json

# Rename a block (shows the diff first), delete one.
wp skouerr rename:block --from=hero --to=hero-banner --format=patch
wp skouerr delete:block --name=old-block --yes --format=json

# Export what the user built in the Site Editor.
wp skouerr save:template --list --format=json
wp skouerr save:template --template=single --delete --format=json
wp skouerr save:pattern --pattern=hero-home --format=json
wp skouerr save:styles --dry-run --format=json
wp skouerr save:navigation --list --format=json
```

If a `Deprecated:` line precedes the JSON (WP-CLI 2.12 on PHP 8.4+), parse from the first line starting with `{`.

After `make:block`, edit the generated `*.template.php|twig`, `*.style.scss` and `*.block.json` in `blocks/<name>/` to implement the design.
