# Skouerr CLI for agents

Skouerr CLI is a set of WP-CLI commands (`wp skouerr …`) that scaffold and maintain Skouerr FSE themes. Every command can run without a human: this page is the contract.

## Discover

```bash
wp skouerr describe              # full manifest: commands, parameters, side effects, exit codes
wp skouerr describe make:block   # one command
wp skouerr info --format=json    # project context: theme paths, blocks, post types, integrations
```

`describe` returns, for each command, its parameters as a schema (`type`, `required`, `default`, `enum`, `pattern`) and its side effects (`filesystem`, `network`, `database`, `process`). Read it instead of guessing options.

## Run

Always pass `--format=json`. It disables prompts and prints exactly one JSON document on STDOUT:

```json
{
  "ok": true,
  "command": "make:block",
  "dry_run": false,
  "message": "Block skouerr/hero created.",
  "data": { "block": { "name": "skouerr/hero", "directory": "/…/themes/acme/blocks/hero" } },
  "files": [ { "action": "create", "path": "/…/blocks/hero/hero.block.json" } ],
  "warnings": [],
  "next_steps": [ "Build the block assets with the theme bundler (e.g. npm run build)." ]
}
```

On failure, `ok` is `false` and `error` explains what to fix:

```json
{
  "ok": false,
  "command": "make:post-type",
  "error": {
    "code": "invalid_input",
    "message": "Missing required parameter --slug (Post type key …).",
    "details": { "parameter": { "name": "slug", "required": true, "pattern": "/^[a-z0-9_-]{1,20}$/D" } }
  }
}
```

Allowed values of an option are in `error.details.parameter.enum`. When a value must be picked from a list (library item, template in the database), `error.details.choices` lists the valid values.

## Exit codes

| Code | `error.code` | Meaning | What to do |
|------|--------------|---------|------------|
| 0 | — | Success | Read `files` and `next_steps`. |
| 1 | `error` | Unexpected failure (write error…) | Report to the user. |
| 2 | `invalid_input` | Missing or invalid parameter | Fix the parameter named in `error.details`. |
| 3 | `conflict` | Target file exists | Ask the user before retrying with `--force`. |
| 4 | `remote_error` | Network, HTTP status, invalid archive, checksum | Retry later or report. |
| 5 | `environment` | Missing requirement (zip extension, library…) | Report to the user. |
| 6 | `aborted` | Confirmation refused | Stop. |

## Safe workflow

1. `wp skouerr info --format=json` to learn the theme.
2. Run the command with `--dry-run --format=json` (or `--format=patch`) and check `files`.
3. Run it again without `--dry-run`.
4. Execute `next_steps` when relevant (asset build, rewrite flush).

Rules:

- Never add `--force` on your own: it overwrites files. Ask first.
- `save:template` and `save:pattern` only touch the database with `--delete` (moves the post to the trash, recoverable).
- `make:theme` and `import:block` download code. Pass `--sha256` when the checksum is known.
- Names (`--name`, `--slug`, `--prefix`, `--icon`) are validated against the `pattern` of the schema: lowercase letters, digits and dashes.

## Commands

| Command | Purpose | Side effects |
|---------|---------|--------------|
| `list` | List the commands | — |
| `describe [<command>]` | Machine-readable manifest | — |
| `info` | Theme context | — |
| `update:core` | Update inc/core to the core shipped with the CLI; files changed in the project need --force | filesystem |
| `agent:install` | Install this skill and an AGENTS.md in the project | filesystem |
| `make:theme` | Download and install the starter theme | network, filesystem, process, database |
| `make:block` | Create a block: `--type=native\|acf\|interactive`, `--template=php\|twig\|react`, `--attributes` (JSON object), `--fields` (ACF, JSON array) | filesystem |
| `make:post-type` | Create a custom post type | filesystem |
| `make:taxonomy` | Create a custom taxonomy | filesystem |
| `make:template` | Create an empty template or template part (`--part`) | filesystem |
| `make:pattern` | Create a pattern (`--content` accepts block markup) | filesystem |
| `make:style-variation` | Create a theme style variation (`styles/<name>.json`) | filesystem |
| `make:block-style` | Create a block style (`styles/blocks/<name>.json`) | filesystem |
| `make:variation` | Create a block variation | filesystem |
| `make:binding` | Create a block binding source | filesystem |
| `list:blocks` | List the theme blocks | — |
| `rename:block` | Rename a block everywhere (`--update-content` for the database) | filesystem, database |
| `delete:block` | Delete a block folder (needs `--yes`), reports remaining usage | filesystem |
| `import:block` | Import a block, pattern or template from a library | network, filesystem |
| `save:template` | Export Site Editor templates to files | filesystem, database |
| `save:pattern` | Export editor patterns to files | filesystem, database |
| `save:styles` | Merge Site Editor global styles into theme.json | filesystem, database |
| `save:navigation` | Export navigation menus as template parts | filesystem |

## Review before writing

`--format=patch` prints a unified diff of every file change and writes nothing. Paths are relative to the root of the Git repository holding the theme (to the theme directory outside a repository). Show it to the user, then apply it with `git apply` from that root, or run the command again without `--format=patch`.

## WordPress abilities

On WordPress 6.9+, the same commands are registered as abilities `skouerr/<command>` (`make:block` → `skouerr/make-block`), available through the REST API (`wp-abilities/v1`) and the WordPress MCP adapter. Input is an object with the option names (`{"title": "Hero", "type": "acf", "dry-run": true}`); output is the `--format=json` document; failures are `WP_Error` with code `skouerr_<error.code>`. `make:theme`, `import:block`, `agent:install` and `update:core` are CLI-only. Abilities that write files need `edit_themes`: with `DISALLOW_FILE_EDIT` (Bedrock default) they fail with `skouerr_file_edit_disabled`, even with `dry-run`; use WP-CLI then.

## WP-CLI 2.12 and PHP 8.4+

WP-CLI 2.12 itself triggers PHP deprecations. One `Deprecated:` line can be printed on STDOUT before the JSON document, at WP-CLI start-up. Run WP-CLI with `php -d display_errors=stderr "$(command -v wp)" skouerr …`, or set `error_reporting = E_ALL & ~E_DEPRECATED` for the CLI. If you cannot, parse STDOUT from the first line that starts with `{`.

## Environment

- Prompts are also disabled by `--no-interaction`, a non-TTY STDIN, `SKOUERR_NO_INTERACTION=1` or `CI=1`.
- `--theme=<slug>` targets another installed theme than the active one.
- Every path in the output is absolute.
