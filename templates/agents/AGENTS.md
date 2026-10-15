# Agent instructions

This is a Skouerr FSE WordPress theme. Scaffold with the `wp skouerr` commands instead of writing files by hand.

- `wp skouerr info --format=json`: theme paths, existing blocks, post types, integrations.
- `wp skouerr describe <command>`: parameters of a command (enum, pattern, default).
- Always pass `--format=json`. Preview with `--dry-run` or `--format=patch`.
- Exit code 2: fix the parameter named in `error.details`. Exit code 3: the file exists, ask before using `--force`.

Skill: `.claude/skills/skouerr-cli/SKILL.md`.
