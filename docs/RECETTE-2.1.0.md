# Recette Skouerr CLI 2.0 → 2.1.0

Projet de test : Glen Moray (Bedrock, WordPress 7.1.3, PHP 8.5.11, WP-CLI 2.12, thème FSE Skouerr `glen-moray`, ACF Pro, sans Timber), le 15 octobre 2026.

Méthode : branche de test jetable, tout ce qui est créé porte le préfixe `zz-test`, aucune écriture sur les contenus réels (`save:*` en `--list` / `--dry-run` uniquement). À la fin, fichiers et posts de test supprimés, `git status --ignored` identique à l'état de départ.

## 1. Recette de la 2.0.0

| # | Point | Résultat |
|---|-------|----------|
| 1 | `list` (22 commandes), `info`, `describe` | OK. `info.theme.is_skouerr` faux sur un thème Skouerr à `class-skouerr-loader.php`. |
| 2 | Messages texte en français, `--format=json` en anglais, un seul document | OK |
| 3 | `list:blocks --format=json` | OK |
| 4 | `make:block` natif, ACF `--fields`, interactive `--attributes`, React | Natif, ACF, interactive : OK avec écarts. React : **KO**, skouerr-scripts ne compile pas `*.editor.js`. |
| 5 | `make:post-type`, `make:taxonomy`, `make:template` (+ `--part`), `make:pattern`, `make:style-variation`, `make:block-style`, `make:variation`, `make:binding` | OK, sauf taxonomie non chargée (`taxonomies/`) et style de bloc jamais enregistré (`"styles": []`). |
| 6 | Conflit (code 3), `--name=../x` (code 2), paramètre manquant sans terminal (code 2) | OK |
| 7 | `rename:block --update-content` puis `delete:block` sur un post de test | OK |
| 8 | `save:template`, `save:pattern`, `save:navigation`, `save:styles` en `--list` / `--dry-run` / `--format=patch` | OK, base inchangée. Message de `save:pattern` faux pour les compositions synchronisées. |
| 9 | Abilities `skouerr/*` visibles dans le MCP local | OK en lecture. Écriture refusée sans explication (Bedrock : `DISALLOW_FILE_EDIT`), `skouerr/info` refusait une exécution sans entrée. |
| 10 | `agent:install --dry-run` | Écrit dans le thème, pas à la racine du projet. `--path` intercepté par WP-CLI. |

## 2. Bugs corrigés en 2.1.0

| Bug | Reproduction (2.0.0) | Correction |
|-----|----------------------|------------|
| `--format=patch` inapplicable sous Bedrock | `make:binding --format=patch > p.diff`, puis `git apply -v p.diff` depuis le thème : « Skipped patch », rien n'est appliqué | Chemins relatifs à la racine du dépôt Git (`Filesystem::patch_path()`), au thème hors dépôt |
| `docs/` absent du paquet | `ls vendor/skouerr/skouerr-cli/docs` | Retrait de `/docs export-ignore` dans `.gitattributes` |
| Style de bloc jamais enregistré | `make:block-style … ` : `"styles": []`, absent de l'éditeur | `"styles": {}` et étape suivante : WordPress ignore un style vide |
| `dist/script.js` chargé deux fois | `viewScript` (natif, Twig, React) alors que `Skouerr_Block::render()` le charge ; `"script": ["dist/script.js"]` (ACF) lu comme un handle | Clés retirées des gabarits |
| Contexte interactif figé | `data-wp-context='{ "open": false }'` quels que soient `--attributes` | `wp_interactivity_data_wp_context()` avec les attributs, libellé du bouton traduisible |
| Bloc React inutilisable sans le dire | `make:block --template=react` + `npx skouerr-scripts build` : pas de `dist/editor.js` | Avertissement quand le thème compile avec skouerr-scripts |
| Groupe ACF hors du point de sauvegarde | `--fields` écrit dans `acf-json/` alors que le projet sauvegarde dans `inc/acf/json/` | Point de sauvegarde ACF (`acf/settings/save_json`) s'il est dans le thème |
| Taxonomie non chargée | `make:taxonomy` écrit dans `taxonomies/`, que le loader ne lit pas | Écrite dans `post-types/` quand seul ce dossier est chargé |
| Options de text domain incohérentes | `make:block --text-domain`, `make:post-type --domain` | `--text-domain` partout, `--domain` conservé comme alias |
| `is_skouerr` faux | `info` sur un thème à `inc/core/class-skouerr-loader.php` | Les deux loaders reconnus. Ajout de `integrations.interactivity` et `content.taxonomies` |
| `agent:install --path` inutilisable | `agent:install --path=/var/www` : « 'skouerr' is not a registered wp command » | Option `--dir`. Par défaut : racine du dépôt Git |
| `save:pattern --delete` casse les compositions synchronisées | Un `wp_block` référencé par `<!-- wp:block {"ref":ID} /-->` partait à la corbeille | Jamais mis à la corbeille s'il est référencé (contenus ou fichiers du thème). Message exact sur la copie conservée |
| Abilities | `skouerr/info` sans entrée : « input n'est pas de type object ». Écriture refusée sans motif | `default: []` dans le schéma. Refus `skouerr_file_edit_disabled` (403) avec la raison |
| Documentation | `error.details.choices` au lieu de `error.details.parameter.enum`, `--theme` annoncé sur `save:*`, résumé de `make:block` daté | Corrigée (README, AGENTS.md, SKILL.md) |

## 3. Vérifications des corrections

- Dépôt CLI : PHPCS propre, PHPStan niveau 8 sans erreur, PHPUnit 99 tests / 304 assertions (15 nouveaux : racine Git, chemins du patch, script non redéclaré, contexte interactif, `update:core`). `bin/check-version.php` : 2.1.0.
- Sur Glen Moray (copie de la CLI corrigée dans `mu-plugins/`, restaurée ensuite) :
  - patch appliqué avec `git apply` depuis la racine et depuis le thème ;
  - 4 blocs enregistrés, compilés, rendus ; `dist/script.js` mis en file une seule fois ; contexte `{"open":false,"label":"Voir"}` ;
  - groupe ACF écrit dans `inc/acf/json/` et chargé ;
  - taxonomie écrite dans `post-types/` et enregistrée ; style de bloc avec `"styles": {}` ;
  - `save:pattern --delete` : composition de test référencée conservée, composition libre mise à la corbeille ; sur `card-cocktail` en dry-run, refus avec « référencée par #8, #262, #342, #344 » (la 2.0.0 l'aurait mise à la corbeille) ;
  - REST : `skouerr/make-block` → 403 `skouerr_file_edit_disabled` ; `skouerr/info` sans entrée → 200, aussi via le MCP local ;
  - `agent:install --dry-run` vise `/var/www/.claude/skills/…` et laisse l'`AGENTS.md` existant intact ;
  - non-régression : rename/delete, make:template/pattern/variation/style-variation, codes 2 et 3.

## 4. Cœur du starter et `update:core`

### Starter theme (`skouerr/skouerr-theme`, branche `feature/core-php85`)

| Fichier | Changement |
|---------|-----------|
| `inc/core/class-skouerr-mail.php`, `mails/` | Supprimés (filtre `wp_mail` qui réécrivait tous les mails, en-têtes perdus, avertissements PHP sur `$args['headers'][0]`). Ligne `require` retirée de `functions.php`, section retirée de la doc. |
| `class-skouerr-loader.php` | `allowed_block_types_all` à `true` ne provoque plus de `TypeError`. Filtre `skouerr_allowed_blocks`. Les patterns déjà enregistrés par WordPress ne sont plus réenregistrés : `Inserter: no`, catégories et types de blocs étaient perdus (la composition 404 de Glen Moray apparaissait dans l'outil d'insertion). Chargement de `taxonomies/`. Variations : dépendance `wp-dom-ready`, version `filemtime`, pied de page. Bindings : `uses_context` (Query Loop). `block.json` invalide ignoré, `glob()` en échec toléré. |
| `class-skouerr-block.php` | `get_data()` / `the_data()` sans avertissement sur une clé absente. `dist/script.js` chargé seulement s'il existe, versionné par `filemtime`. Rendu React : données encodées sans risque de `</script>`. Twig absent : `_doing_it_wrong()` au lieu d'une erreur fatale. |
| `dev.php` | Port par défaut 4100 (celui de `package.json`) dans le filtre, au lieu d'un `add_filter` figé dans le cœur. `WP_DEBUG` / `WP_DEBUG_LOG` lus sans erreur s'ils ne sont pas définis. |
| `setup.php` | Pattern d'accueil `home-default.php` inexistant (erreur fatale de `wp skouerr-theme setup`) : `hero-home.php`, filtre `skouerr_setup_home_pattern`. |
| `blocks/documentation` | Fichier lu seulement dans le thème (pas de `..`), avertissements libxml masqués, `$currentH1` initialisé. |
| `composer.json` | PHP ≥ 8.1. Retrait des dépendances inutilisées (`knplabs/github-api`, `symfony/http-client`, `nyholm/psr7`, `twig/extra-bundle`, et la pile Symfony qu'elles tiraient). Twig 3.30, Parsedown 1.8. `wptrt/wpthemereview` (dépend de WPCS 2, avis de sécurité) remplacé par WPCS 3 et PHPCompatibilityWP. |
| `inc/core/skouerr-core.json` | Nouveau : version du cœur (2.0.0) et empreinte de chaque fichier. |

Contrôles en PHP 8.5.11 : `parallel-lint` sans erreur, PHPCompatibilityWP (8.1+) sans erreur, `composer audit` propre, rendu Twig et Parsedown sans avertissement en `E_ALL`.

### `wp skouerr update:core`

- Fichiers de référence livrés dans `templates/core/`, produits par `php bin/sync-core.php <starter> <version>` (copie de `inc/core/`, empreintes de toutes les versions de l'historique du starter, fichiers retirés avec leurs classes, anciens noms de fichiers).
- Statuts par fichier : `create`, `update` (fichier non modifié, reconnu par le manifeste du thème ou l'historique du starter), `up-to-date`, `modified` (laissé tel quel), `overwrite` (`--force`), `remove`, `keep`.
- Fichier retiré : supprimé avec sa ligne `require` de `functions.php`. Avertissement si une de ses classes est encore utilisée dans le thème.
- Ancienne organisation (`inc/core/loader.php`) : refus, pas de migration automatique. Thème non Skouerr : code 5.
- Confirmation exigée (`--yes`) dès qu'un fichier est supprimé ou écrasé. Réservée à WP-CLI (pas d'ability).

### Test sur Glen Moray (branche jetable, annulée ensuite)

1. `update:core --dry-run` sur l'état réel : `class-skouerr-block.php` reconnu comme non modifié (version du starter) → mis à jour ; `class-skouerr-loader.php`, `dev.php`, `setup.php` modifiés par le projet → laissés tels quels ; `class-skouerr-mail.php` modifié (instanciation commentée) → conservé.
2. Code du projet déplacé dans `inc/hook.php` via les filtres du cœur : `hero-slider` réservé à l'accueil (`skouerr_allowed_blocks`), styles `fill` / `outline` retirés de `core/button`, port 4022 (`skouerr_scripts_port`).
3. `update:core --force --yes` : 4 fichiers écrits, `class-skouerr-mail.php` supprimé, `require` retiré de `functions.php`, manifeste créé. Une relance donne « Core is up to date (2.0.0) ».
4. Vérifications :
   - 8 pages (accueil, contact, listes, fiches produit et cocktail, 404) : mêmes codes HTTP et même HTML qu'avant. Les écarts des fiches cocktail viennent de « Discover more » trié par date alors que tous les cocktails ont la même date : l'ancien cœur donne les mêmes écarts d'une requête à l'autre ;
   - 12 blocs enregistrés, mêmes scripts de blocs sur les pages ;
   - composition `skouerr/404` : `inserter` passe à `false` (corrigé) ;
   - `hero-slider` autorisé sur l'accueil et pas sur Contact, liste des blocs autorisés identique à l'ancien cœur (test A/B) ;
   - bindings avec `uses_context`, port 4022, variations avec `wp-dom-ready` ;
   - aucune nouvelle ligne PHP dans `debug.log`.
5. Non vérifié : l'éditeur de blocs dans le navigateur (connexion requise), `wp skouerr-theme setup`, un thème neuf créé depuis le starter.

## 5. Restes ouverts

- skouerr-scripts : compiler `src/<nom>.editor.js` en `dist/editor.js` (correctif à faire dans skouerr-scripts, pas dans la CLI).
- `make:block-style` écrit dans `styles/blocks/` ; certains projets rangent leurs styles par bloc (`styles/<bloc>/`). Une option de dossier serait utile.
- Gabarits de bloc : pas de `get_block_wrapper_attributes()`, description ACF en français en dur.
- Une taxonomie écrite dans `post-types/` apparaît dans `info.content.post_types`.
- `rename:block --update-content` met à jour la base sans révision (choix assumé : pas de filtre kses sur le contenu).
- Résumés de `wp skouerr list` non traduits ; message « paramètre manquant » en français qui reprend la description anglaise.
- `describe.exit_codes` : liste dont l'index est le code, sans le dire.
- Gabarit `binding/default` : espaces en fin de ligne (`git apply` le signale).
- Côté Glen Moray : `style.css` déclare `Text Domain: theme_utils` au lieu de `glenmoray`.
- Glen Moray : la migration réelle du cœur reste à faire (code du projet dans `inc/`, menus de `setup.php` sur `skouerr_theme_setup_after`, puis `update:core --force`). Cocktails importés avec la même date de publication : ordre instable dans les listes triées par date.
- `make:theme` installe la dernière version du starter publiée sur le serveur de téléchargement : à republier après la fusion de `feature/core-php85`.
