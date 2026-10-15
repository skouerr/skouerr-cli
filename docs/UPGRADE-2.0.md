# Migrer de Skouerr CLI 1.x vers 2.0

Durée estimée : 15 minutes par projet. Les fichiers générés par la 1.x (blocs, post types, bindings, variations, templates) restent valides : rien à régénérer.

## 1. Vérifier les prérequis

```bash
php -v          # 8.3 ou plus
wp cli version  # 2.x (testé avec 2.12.0)
php -m | grep zip
```

Sous PHP 8.4 et 8.5, WP-CLI 2.12 affiche des messages `Deprecated` qui viennent de son propre code. Skouerr CLI masque ceux émis pendant ses commandes. Une ligne reste au démarrage de WP-CLI, avant le chargement du plugin. Pour une sortie propre (indispensable pour `--format=json` dans un script), ajouter au `php.ini` de la CLI :

```ini
error_reporting = E_ALL & ~E_DEPRECATED
```

ou lancer WP-CLI avec `php -d display_errors=stderr $(command -v wp) …`.

## 2. Mettre à jour le paquet

Projet Bedrock / Composer :

```bash
composer require skouerr/skouerr-cli:^2.0
```

Installation copiée à la main dans `wp-content/mu-plugins/skouerr-cli/` : remplacer le dossier entier. L'ancien dossier `vendor/` du plugin n'est plus nécessaire (aucune dépendance à l'exécution).

Vérifier :

```bash
wp skouerr list
wp skouerr info
```

## 3. Adapter les scripts et la CI

| Usage 1.x | 2.0 |
|-----------|-----|
| `wp skouerr save:template` dans un script : supprimait la copie en base | Ajouter `--delete` (la copie part à la corbeille, pas en suppression définitive) |
| `wp skouerr save:pattern` idem | Ajouter `--delete` |
| `wp skouerr make:theme …` activait le thème | Ajouter `--activate` |
| Un fichier existant était écrasé | Erreur (code 3) : ajouter `--force` si l'écrasement est voulu |
| Un paramètre manquant ouvrait une question, même en CI | Erreur (code 2) sans terminal interactif : passer toutes les options |
| `make:block` en natif + Twig plantait | Fonctionne |

Les options de la 1.x restent acceptées (`--name`, `--title`, `--text_domain`, `--library`, `--type`…).

## 4. Code qui utilisait les classes de la 1.x

- `SK_CLI_Input::ask()`, `::select()`, `::select_multiple()` : conservés (dépréciés), ils fonctionnent sans Symfony. Les anciennes commandes `skouerr-theme` des starters continuent de marcher.
- Les classes `Skouerr_CLI`, `Skouerr_CLI_*`, `Skouerr_Template_Block*` sont supprimées. Rechercher leur usage :

```bash
grep -rn "Skouerr_CLI\|Skouerr_Template_Block" web/app/themes wp-content/themes 2>/dev/null
```

## 5. Gabarits personnalisés

Si l'équipe avait modifié les gabarits dans le plugin (fork), les déplacer dans le thème : `<thème>/.skouerr/templates/` reprend l'arborescence de `templates/` du plugin (par exemple `.skouerr/templates/block/native-php/template`). Ils survivent alors aux mises à jour.

Deux nouveaux marqueurs sont disponibles dans `block.json` : `%SK_BLOCK_TEXTDOMAIN%` et `%SK_BLOCK_CATEGORY%`.

## 6. Nouveautés à activer (facultatif)

- `wp skouerr agent:install` : installe le skill Claude Code et un `AGENTS.md` dans le projet.
- Abilities API (WordPress 6.9+) : les commandes sont exposées sous `skouerr/*` (REST `wp-abilities/v1`, MCP). Rien à configurer. Seuls les utilisateurs qui ont la capacité `edit_themes` peuvent lancer celles qui écrivent des fichiers. `DISALLOW_FILE_EDIT` les désactive.
- Bibliothèques privées : constantes `SKOUERR_ALLOWED_LIBRARIES` et `SKOUERR_LIBRARY_TOKENS` (voir le README).

## Retour arrière

```bash
composer require skouerr/skouerr-cli:^1.1
```

Les fichiers produits par la 2.0 (blocs, patterns, styles) restent utilisables avec la 1.x.
