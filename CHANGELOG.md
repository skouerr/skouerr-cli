# Changelog

## 2.0.0 — non publiée

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
