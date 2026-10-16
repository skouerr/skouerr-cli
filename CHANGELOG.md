# Changelog

## 2.1.0 — 2026-10-15

Nouvelle commande `update:core` et corrections issues de la recette sur un projet Bedrock (Glen Moray).

### Ajouts

- `update:core` : met à jour `inc/core/` avec le cœur du starter theme livré dans `templates/core/` (version 2.0.0 : compatible PHP 8.5, sans `Skouerr_Mail`). Les fichiers inchangés sont reconnus par le manifeste `inc/core/skouerr-core.json` ou l'historique du starter ; les fichiers modifiés dans le projet ne sont remplacés qu'avec `--force`. Un fichier retiré du cœur est supprimé avec sa ligne `require` de `functions.php`. Réservée à la ligne de commande.
- `bin/sync-core.php` : copie le cœur d'un starter theme dans la CLI et calcule son historique.

### Corrections

- `--format=patch` : chemins relatifs à la racine du dépôt Git qui contient le thème. Sous Bedrock, `git apply` ignorait sans erreur les chemins relatifs au thème.
- `docs/` est de nouveau livré dans le paquet : les liens du README et du CHANGELOG fonctionnent.
- `make:block-style` : `"styles"` reste un objet JSON (il devenait `[]`), et une étape suivante rappelle que WordPress n'enregistre pas un style vide.
- `make:block` : plus de `viewScript` (natif, Twig, React) ni de `script` (ACF) sur `dist/script.js`. `Skouerr_Block::render()` charge déjà ce fichier : il était chargé deux fois, et la valeur ACF sans `file:` n'était pas un chemin.
- `make:block --type=interactive` : le contexte `data-wp-context` reprend les attributs du bloc (`wp_interactivity_data_wp_context()`), le libellé du bouton est traduisible.
- `make:block --template=react` : avertissement quand le thème compile avec skouerr-scripts, qui ne compile pas `*.editor.js`.
- `make:block --fields` : le groupe ACF va dans le point de sauvegarde ACF (`acf/settings/save_json`) quand il est dans le thème, sinon dans `acf-json/`.
- `make:taxonomy` : si le loader du thème ne charge que `post-types/`, la taxonomie y est écrite et elle est chargée.
- `make:post-type`, `make:taxonomy` : option `--text-domain` comme `make:block` (`--domain` reste un alias).
- `info` : `is_skouerr` reconnaît `inc/core/class-skouerr-loader.php`. Ajout de `integrations.interactivity` et `content.taxonomies`.
- `agent:install` : l'option `--path`, interceptée par WP-CLI, devient `--dir`. Par défaut, les fichiers vont à la racine du dépôt Git qui contient le thème.
- `save:pattern` : un pattern synchronisé encore référencé par un `wp:block` (contenus ou fichiers du thème) n'est jamais mis à la corbeille. Le message sur la copie conservée est exact pour les patterns.
- Abilities : `skouerr/info` et `skouerr/list-blocks` s'exécutent sans entrée. Refus explicite (`skouerr_file_edit_disabled`) quand `DISALLOW_FILE_EDIT` ou `DISALLOW_FILE_MODS` interdit l'écriture.
- Documentation : `error.details.parameter.enum`, `--theme` non disponible sur `save:*`, résumé de `make:block` à jour.

## 2.0.0 — 2026-10-15

Réécriture complète. Voir [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md) pour le détail des failles corrigées.

### Sécurité

- `make:theme` : plus d'injection de commande shell. Composer est lancé sans shell.
- `save:pattern` : le titre d'un pattern ne peut plus injecter de PHP dans le fichier généré.
- Valeurs échappées selon le type de fichier généré (PHP, JS, JSON). Noms validés : plus de traversée de répertoire.
- Téléchargements : HTTPS obligatoire (hors hôtes locaux), code HTTP vérifié, taille limitée, empreinte SHA-256 vérifiée si connue, extraction ZIP contrôlée (pas de `..`, de lien symbolique ni de `.htaccess`).
- Plus aucun fichier écrasé sans `--force`. Plus de dossier `<thème>/tmp` supprimé.
- Les données en base ne sont plus supprimées définitivement : `--delete` envoie à la corbeille.
- Dépendances de développement à jour (`composer audit` propre). CI durcie.

### Ajouts

- Abilities API (WordPress 6.9+) : les commandes sont exposées sous `skouerr/*` pour l'API REST et le MCP Adapter. Il faut la capacité `edit_themes` pour celles qui écrivent des fichiers.
- Nouvelles commandes : `make:pattern`, `make:taxonomy`, `make:style-variation`, `make:block-style`, `rename:block`, `delete:block`, `save:styles`, `save:navigation`, `agent:install`.
- `make:block` :
  - type `interactive` (Interactivity API) et gabarit `react` (éditeur JSX) ;
  - `--attributes` (JSON fusionné dans `block.json`) et `--fields` (groupe de champs ACF dans `acf-json/`, avec des clés stables) ;
  - `--text-domain` et `--category`.
- `--format=patch` : diff unifié des fichiers, applicable avec `git apply`, sans rien écrire.
- Gabarits surchargeables par le thème (`<thème>/.skouerr/templates/`) et par le filtre `skouerr_cli_templates_directory`.
- Bibliothèques : liste blanche (`SKOUERR_ALLOWED_LIBRARIES`) et jetons pour les bibliothèques privées (`SKOUERR_LIBRARY_TOKENS`, filtre `skouerr_library_token`).
- Messages traduits en français. La sortie JSON reste en anglais.
- Les caches de WordPress (patterns, theme.json) sont vidés après chaque écriture.
- WP-CLI 2.12 sous PHP 8.4+ : les dépréciations émises par WP-CLI pendant les commandes sont masquées, et les erreurs PHP vont sur STDERR en mode JSON.
- Couche de compatibilité `SK_CLI_Input` (dépréciée) pour le code qui l'utilisait.
- Release GitHub automatique sur tag, contrôle de cohérence des versions (`bin/check-version.php`).
- Guide de migration : [docs/UPGRADE-2.0.md](docs/UPGRADE-2.0.md).
- Sortie JSON (`--format=json`), mode non interactif automatique (pas de TTY, `CI`, `SKOUERR_NO_INTERACTION`), `--dry-run`, `--yes`, codes de sortie documentés.
- Nouvelles commandes : `describe` (manifeste JSON pour les agents) et `info` (contexte du thème).
- `--theme=<slug>` pour cibler un thème non actif.
- `make:template --part`, `make:post-type --plural`, `save:template --part`, `--list` et `--all` sur `save:*` et `import:block`.
- Avertissements quand ACF Pro ou Timber manquent, quand une dashicon est inconnue.
- Skill Claude Code (`skills/skouerr-cli/SKILL.md`) et guide agents (`docs/AGENTS.md`).
- Tests unitaires (PHPUnit), analyse statique (PHPStan niveau 8), tests de bout en bout dans WordPress, dont `make:theme` et `import:block` contre un faux serveur.

### Corrections

- `make:block` en natif + Twig plantait (classe non chargée).
- `make:binding`, `make:variation` échouaient si le dossier parent n'existait pas.
- `save:pattern` cassait le balisage des blocs (`wp_kses_post`).
- `save:template` proposait les templates des autres thèmes.
- `import:block` : erreur PHP si `--library` absent.

### Changements incompatibles

- PHP 8.3 minimum (au lieu de 8.0).
- Plus de dépendance à `symfony/console`, `symfony/string`, `symfony/finder`. `vendor/` n'est plus versionné.
- Classes renommées et namespacées (`Skouerr\Cli\…`). Les classes `Skouerr_CLI_*` et `Skouerr_Template_Block*` n'existent plus (`SK_CLI_Input` est conservée, dépréciée).
- `save:template` et `save:pattern` ne suppriment plus la copie en base par défaut en mode non interactif : passer `--delete`.
- `make:theme` n'active plus le thème sans `--activate` (ou confirmation interactive).
- Les fichiers de `save:template` / `save:pattern` vont dans le thème enfant actif (`get_stylesheet_directory()`), là où WordPress lit ces templates.

## 1.1.0

- Mise à jour des gabarits, ajout de `make:binding`.
