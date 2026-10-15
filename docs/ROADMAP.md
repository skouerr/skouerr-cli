# Évolutions

Suivi des évolutions proposées après l'audit de la version 1.1.0.

## Livrées dans la 2.0

| # | Évolution | Où |
|---|-----------|----|
| 1 | Commandes exposées via l'Abilities API (REST `wp-abilities/v1`, MCP) | `src/Abilities.php` |
| 2 | `make:block --attributes` (block.json) et `--fields` (groupe ACF dans `acf-json/`, clés stables) | `src/Scaffold/BlockSchema.php` |
| 3 | `save:styles` : styles globaux de l'éditeur de site fusionnés dans `theme.json` | `src/Command/SaveStylesCommand.php` |
| 4 | Text domain et catégorie des blocs configurables (`--text-domain`, `--category`) | gabarits `block.json` |
| 5 | Gabarits surchargeables par le thème (`.skouerr/templates/`) et par filtre (`skouerr_cli_templates_directory`) | `src/Support/Templates.php` |
| 6 | `agent:install` : skill Claude Code + `AGENTS.md` | `src/Command/AgentInstallCommand.php` |
| 7 | `make:pattern` | `src/Command/MakePatternCommand.php` |
| 8 | `make:style-variation` et `make:block-style` (`styles/blocks/*.json`, WordPress 6.6+) | `src/Command/MakeStyleCommand.php` |
| 9 | `make:taxonomy` | `src/Command/MakeTaxonomyCommand.php` |
| 10 | Blocs Interactivity API (`--type=interactive`) et éditeur React/JSX (`--template=react`) | `templates/block/` |
| 11 | `rename:block` (fichiers, balisage du thème, contenu en base avec `--update-content`) et `delete:block` | `src/Command/RenameBlockCommand.php`, `DeleteBlockCommand.php` |
| 12 | `save:navigation` : menus `wp_navigation` en parties de modèle | `src/Command/SaveNavigationCommand.php` |
| 15 | `--format=patch` : diff unifié applicable avec `git apply`, rien n'est écrit | `src/Support/Diff.php` |
| 16 | Messages traduits en français pour les humains, JSON toujours en anglais | `languages/` |
| 17 | Tests de bout en bout de `make:theme` et `import:block` avec un faux serveur | `tests/integration/library.sh` |
| 20 | Liste blanche de bibliothèques et jetons pour les bibliothèques privées | `src/Support/Libraries.php` |
| 21 | Release GitHub automatique sur tag `v*`, contrôle de cohérence des versions | `.github/workflows/release.yml`, `bin/check-version.php` |

## Écartées

| # | Évolution | Décision |
|---|-----------|----------|
| 13 | Commande `doctor` | Utilité incertaine. `info` couvre le contexte. |
| 14 | Commande `apply <plan.json>` | Utilité incertaine. |
| 18 | Signature Ed25519 des archives | Non retenue. |
| 19 | En-tête `X-Skouerr-Sha256` côté serveur | Non retenue. La CLI le vérifie s'il est présent, et `--sha256` reste disponible. |

## Pistes suivantes

- Générer le contenu des gabarits de bloc à partir de `--fields` (variables ACF déjà déclarées dans le template PHP ou Twig).
- Passer à WP-CLI 2.13 dès sa sortie, puis retirer le contournement des dépréciations PHP 8.5.
- Ajouter une commande `make:part` dédiée (aujourd'hui `make:template --part`).

## À surveiller

- **WP-CLI 2.12 sous PHP 8.4+** : le code de WP-CLI émet des dépréciations. Skouerr CLI masque celles qui surviennent pendant ses commandes. Une seule ligne reste, émise au démarrage de WP-CLI, avant le chargement du plugin. La CI teste WP-CLI 2.12.0 épinglé. Voir [UPGRADE-2.0.md](UPGRADE-2.0.md#1-vérifier-les-prérequis).
- **Starter theme** : vérifier la compatibilité PHP 8.5 de `skouerr-theme setup` et de `Skouerr_Loader`. `make:taxonomy` prévient si le loader ne charge pas `taxonomies/`.
