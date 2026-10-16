# Skouerr CLI

Commandes WP-CLI pour créer et maintenir les thèmes FSE Skouerr : thème, blocs, post types, templates, variations, bindings, import depuis une bibliothèque, export des templates et patterns édités dans l'éditeur de site.

La CLI est pensée pour les humains **et** pour les agents IA : sortie JSON, mode non interactif, `--dry-run`, `--format=patch`, codes de sortie documentés, manifeste auto-descriptif (`wp skouerr describe`) et abilities WordPress.

## Prérequis

- PHP 8.3 ou plus (testé sur 8.3, 8.4 et 8.5)
- WordPress 6.5 ou plus (6.9 pour l'Abilities API), WP-CLI 2.x (testé avec 2.12.0)
- Extension PHP `zip` pour `make:theme` et `import:block`
- Aucune dépendance Composer à l'exécution

## Installation

Avec Composer (Bedrock) :

```bash
composer require skouerr/skouerr-cli
```

Le paquet est de type `wordpress-muplugin`. Sans Composer, copier le dossier dans `wp-content/mu-plugins/skouerr-cli/` et le charger depuis un fichier `wp-content/mu-plugins/load-skouerr-cli.php` :

```php
<?php
require __DIR__ . '/skouerr-cli/skouerr-cli.php';
```

## Commandes

| Commande | Rôle |
|----------|------|
| `info` | Contexte du thème : chemins, blocs, post types, intégrations |
| `list`, `describe` | Liste des commandes, manifeste JSON pour les agents |
| `make:theme` | Télécharge et installe le starter theme |
| `make:block` | Bloc natif, ACF ou Interactivity API ; gabarit PHP, Twig ou éditeur React |
| `make:post-type`, `make:taxonomy` | Type de contenu, taxonomie |
| `make:template` | Modèle ou partie de modèle (`--part`) |
| `make:pattern` | Pattern dans `patterns/` |
| `make:style-variation`, `make:block-style` | Variation de style du thème, style de bloc (`styles/blocks/`) |
| `make:variation`, `make:binding` | Variation de bloc, source de block binding |
| `list:blocks`, `rename:block`, `delete:block` | Gestion des blocs du thème |
| `import:block` | Import depuis une bibliothèque Skouerr |
| `save:template`, `save:pattern`, `save:styles`, `save:navigation` | Export vers le thème de ce qui a été fait dans l'éditeur de site |
| `update:core` | Met à jour `inc/core/` (loader, rendu des blocs, outils de dev, setup) avec la version livrée par la CLI |
| `agent:install` | Skill Claude Code et `AGENTS.md` dans le projet |

```bash
wp skouerr list
wp help skouerr make:block
```

Sans option, chaque commande pose ses questions dans le terminal. Toutes les questions ont une option équivalente :

```bash
wp skouerr make:block --title="Hero Banner" --type=native --template=twig --icon=cover-image
wp skouerr make:block --title="Team" --type=acf --fields='[{"name":"heading","type":"text"}]'
wp skouerr make:block --title="Accordion" --type=interactive --attributes='{"open":{"type":"boolean"}}'
wp skouerr make:post-type --slug=event --label=Event --plural=Events
wp skouerr make:taxonomy --slug=event-type --label="Event type" --post-types=event --hierarchical
wp skouerr make:pattern --title="Hero home" --categories=featured
wp skouerr make:block-style --title="Outline" --block-types=core/button
wp skouerr rename:block --from=hero --to=hero-banner --format=patch
wp skouerr save:styles --delete
wp skouerr make:theme --title="Acme" --name=acme --activate
```

Options communes :

| Option | Effet |
|--------|-------|
| `--format=json` | Un seul document JSON sur la sortie standard, en anglais, aucune question |
| `--format=patch` | Diff unifié des fichiers, rien n'est écrit. Chemins relatifs à la racine du dépôt Git qui contient le thème (au dossier du thème hors dépôt) : appliquer avec `git apply` depuis cette racine |
| `--no-interaction` | Aucune question : erreur si un paramètre obligatoire manque |
| `--yes` | Répond oui aux confirmations |
| `--dry-run` | Affiche les fichiers qui seraient écrits, sans rien écrire |
| `--force` | Écrase les fichiers existants (refusé par défaut) |
| `--theme=<slug>` | Cible un autre thème installé que le thème actif (sauf `save:*`, qui écrivent dans le thème enfant actif) |

Les messages sont en français si le site est en français (`languages/`).

## Mise à jour du cœur du thème

`wp skouerr update:core` remplace les fichiers de `inc/core/` par ceux du starter theme livrés avec la CLI (`templates/core/`).

- Un fichier inchangé depuis son installation est mis à jour. Il est reconnu grâce à `inc/core/skouerr-core.json` (écrit par la commande) ou à l'historique du starter (thèmes plus anciens).
- Un fichier modifié dans le projet n'est jamais remplacé sans `--force`. Déplacer le code propre au projet dans `inc/`, avec les filtres du cœur (`skouerr_allowed_blocks`, `skouerr_scripts_port`…).
- Un fichier retiré du cœur (`class-skouerr-mail.php`) est supprimé, et sa ligne `require` retirée de `functions.php`.
- Les thèmes à l'ancienne organisation (`inc/core/loader.php`) ne sont pas migrés.

```bash
wp skouerr update:core --format=patch          # diff des fichiers mis à jour
wp skouerr update:core --force --format=patch  # diff complet, fichiers modifiés compris
wp skouerr update:core --yes
```

Mettre à jour le cœur livré : `php bin/sync-core.php /chemin/vers/skouerr-theme <version>` (copie `inc/core/`, calcule l'historique, écrit le manifeste du starter).

## Abilities API

Sous WordPress 6.9 et plus, les commandes sont aussi exposées comme abilities `skouerr/*` (par exemple `skouerr/make-block`). Elles sont disponibles via l'API REST `wp-abilities/v1` et via le MCP Adapter de WordPress. Elles renvoient le même document que `--format=json`.

- Les commandes qui écrivent des fichiers demandent la capacité `edit_themes`. `DISALLOW_FILE_EDIT` (actif par défaut sous Bedrock) les désactive, même en `dry-run` : l'ability renvoie alors l'erreur `skouerr_file_edit_disabled`. Passer par WP-CLI.
- `make:theme`, `import:block`, `agent:install` et `update:core` restent réservées à la ligne de commande.

## Utilisation par un agent IA

Voir [docs/AGENTS.md](docs/AGENTS.md) : format JSON, codes de sortie, workflow conseillé.

`wp skouerr agent:install` installe le skill Claude Code ([skills/skouerr-cli/SKILL.md](skills/skouerr-cli/SKILL.md)) et un `AGENTS.md` dans le projet : la racine du dépôt Git qui contient le thème, sinon le thème. `--dir=<dossier>` pour en choisir un autre (`--path` est réservé par WP-CLI). Un `AGENTS.md` existant n'est jamais modifié.

## Configuration

| Réglage | Où | Défaut |
|---------|----|--------|
| `SKOUERR_REMOTE_DOMAIN` | constante, `wp-config.php` | `https://download.skouerr.dev` |
| `SKOUERR_REMOTE_THEME_URL` | constante | `<domaine>/wp-json/skouerr/v1/download/theme` |
| `SKOUERR_ALLOWED_LIBRARIES` | constante (tableau ou liste séparée par des virgules) | aucune restriction |
| `SKOUERR_LIBRARY_TOKENS` | constante : `array( 'https://lib.example.com' => 'jeton' )` | aucun |
| `skouerr_import_libraries` | filtre | `SKOUERR_ALLOWED_LIBRARIES`, sinon `http://localhost` |
| `skouerr_library_token` | filtre `( $token, $url )` | — |
| `skouerr_cli_templates_directory` | filtre `( $directory, $theme_directory )` | — |

- **Téléchargements** : HTTPS est obligatoire, sauf pour les hôtes locaux (`localhost`, `*.test`, `*.local`, `*.ddev.site`, `*.lndo.site`). Si le serveur envoie l'en-tête `X-Skouerr-Sha256`, l'empreinte de l'archive est vérifiée. Elle peut aussi être passée avec `--sha256`.
- **Bibliothèques privées** : un jeton n'est envoyé qu'à sa propre bibliothèque, et jamais à travers une redirection.

**Gabarits** : un thème peut remplacer n'importe quel gabarit en reprenant l'arborescence de `templates/` dans `<thème>/.skouerr/templates/`.

## Migration depuis la 1.x

Voir [docs/UPGRADE-2.0.md](docs/UPGRADE-2.0.md).

## Développement

```bash
composer install
composer check          # PHPCS + PHPStan (niveau 8) + PHPUnit
tests/integration/smoke.sh /chemin/vers/wordpress     # tests de bout en bout
tests/integration/library.sh /chemin/vers/wordpress   # make:theme et import:block contre un faux serveur
```

Publier une version : mettre le même numéro dans `composer.json`, l'en-tête et la constante de `skouerr-cli.php` et `tests/phpstan-bootstrap.php` (`php bin/check-version.php` le vérifie), ajouter la section dans `CHANGELOG.md`, puis pousser un tag `vX.Y.Z`. La release GitHub et Packagist suivent automatiquement.

- [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md) : audit de sécurité de la version 1.1.0
- [docs/ROADMAP.md](docs/ROADMAP.md) : évolutions livrées et écartées
- [CHANGELOG.md](CHANGELOG.md)
