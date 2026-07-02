# Journal de bord — Démo MongoDB Automated Embedding (EasyAdminBundle)

Matière brute pour l'article. Chaque entrée : ce qui a été tenté, ce qui a marché, ce qui n'a pas
marché, et pourquoi.

## 2026-07-02 — Scaffold du projet

- `symfony new easyadmin-vector-demo --version=lts` → Symfony 7.4.
- Ajout d'un repository VCS Composer vers `https://github.com/GromNaN/mongodb-odm.git` pour installer
  `doctrine/mongodb-odm: dev-autoembedding as 2.17.x-dev` (la PR [#3024](https://github.com/doctrine/mongodb-odm/pull/3024)
  n'est pas mergée). L'alias `as 2.17.x-dev` est nécessaire pour satisfaire les contraintes de version
  de `doctrine/mongodb-odm-bundle` qui exige `^2.x`/`^3.x` numéroté, pas juste `dev-autoembedding`.
  **Ça a marché du premier coup** — composer a résolu et installé sans conflit.
- `doctrine/mongodb-odm-bundle ^5.6`, `symfony/twig-bundle`, `symfony/http-client`,
  `symfony/mcp-bundle ^0.10`, `nikic/php-parser ^5.7`, `league/commonmark ^2.8` installés sans accroc.
- **Piège MCP bundle** : la doc publique montre `config/packages/mcp.yaml` avec les clés `app`,
  `version`, `description`, `client_transports: {stdio, http}` — confirmé exact via
  `bin/console config:dump-reference mcp` une fois le bundle installé (bonne pratique : toujours
  vérifier avec `config:dump-reference` plutôt que de faire confiance à la doc au mot près, les
  options peuvent changer entre versions).
- **Piège routing** : le recipe Flex du mcp-bundle n'a *pas* généré automatiquement l'entrée de
  routing `type: mcp` dans `config/routes.yaml` (recipe marquée "auto-generated", pas une recipe
  officielle). Sans cette entrée, `bin/console debug:router` échoue avec
  `Cannot load resource "." — no loader supporting the "mcp" type`, alors même que le bundle est bien
  enregistré dans `config/bundles.php`. Il faut l'ajouter à la main :
  ```yaml
  mcp:
      resource: .
      type: mcp
  ```
  Après ajout + `cache:clear`, la route `_mcp_endpoint` (`GET|POST|DELETE|OPTIONS /_mcp`) apparaît
  bien dans `debug:router`. **Point à mentionner dans l'article** : ne pas se fier uniquement au
  `composer require`, vérifier `debug:router` après coup.
- Namespace réel de l'attribut de tool confirmé dans le SDK installé :
  `Mcp\Capability\Attribute\McpTool` (cohérent avec la doc).
- **Non résolu à ce stade** : je n'ai pas d'accès en écriture/lecture à `.env`/`.env.local` depuis cet
  environnement (bloqué par les permissions de l'outil, protection anti-fuite de secrets). Jérôme doit
  renseigner lui-même dans `.env.local` : `MONGODB_URI`, `MONGODB_DB`, et les futures variables
  `VOYAGE_API_KEY` (si nécessaire côté self-managed) / `GITHUB_TOKEN` (pour l'ingestion, au-delà des
  60 req/h anonymes de l'API GitHub).

## 2026-07-02 — Modèle de données ODM

- Le mapping `#[VectorSearchIndex]` de la PR utilise `name` (pas `indexName` comme je l'avais
  d'abord supposé dans le plan) et un tableau `fields` où chaque entrée a `type: autoEmbed|vector|filter`.
  Confirmé en lisant directement `vendor/doctrine/mongodb-odm/src/Mapping/Attribute/VectorSearchIndex.php`
  une fois le package installé — plus fiable que de deviner depuis la description de la PR.
  **Leçon pour l'article** : toujours vérifier la signature réelle dans le code installé plutôt que de
  se fier à l'exemple donné dans la description d'une PR (qui peut dater d'une itération antérieure).
- `bin/console doctrine:mongodb:mapping:info` valide le mapping (6 documents reconnus) **sans avoir
  besoin d'une connexion Atlas active** — pratique pour itérer sur le schéma avant de brancher le vrai
  cluster.

## 2026-07-02 — Chunking

- Prembattage important détecté au smoke-test : un paragraphe (Markdown) ou une section (RST) *sans
  aucune ligne vide interne* est traité comme un bloc atomique unique. Si ce bloc dépasse à lui seul
  la taille cible (350 tokens), le découpage naïf par blocs sémantiques ne peut pas le scinder — testé
  avec un paragraphe artificiel de ~1200 tokens sans saut de ligne : il ressortait comme un seul chunk
  de 1227 tokens, largement au-dessus de la cible.
  **Correctif** : `BlockPacker` pré-découpe désormais tout bloc trop long avec une fenêtre de mots à
  taille fixe et ~15% de recouvrement (la stratégie "fixed token count with overlap" recommandée pour
  du contenu non structuré) avant le passage de compactage sémantique normal. Après correctif, le même
  test donne 7 chunks de ~150-370 tokens. **Point d'article** : le chunking par frontières sémantiques
  seul ne suffit pas, il faut toujours un filet de sécurité en fixed-window pour les cas dégénérés
  (gros pavé de texte collé sans mise en forme, trace de pile complète, etc. — fréquent dans des
  commentaires GitHub).
- Pour le code PHP, `nikic/php-parser` (AST) capture correctement une méthode + son docblock comme un
  chunk, mais un premier test a révélé un angle mort : le docblock **de la classe elle-même**
  (ex. `/** Main admin controller. */`) était perdu dès que la classe avait au moins une méthode
  concrète (le chunk "classe entière" n'était émis que pour les classes sans méthode). Or ce docblock
  de classe contient souvent la description la plus utile pour la recherche sémantique ("à quoi sert
  ce contrôleur"). **Correctif** : émission systématique d'un petit chunk `class_doc` (docblock +
  ligne de signature) dès qu'une classe a un docblock, en plus des chunks par méthode.

## 2026-07-02 — Premier index autoEmbed créé sur Atlas (succès)

- `git clone --depth 1 EasyCorp/EasyAdminBundle` dans `var/repo-cache/EasyAdminBundle`.
- `app:ingest:doc --path=.../doc` : 42 pages `.rst` → 407 chunks écrits dans `chunks`, sans problème.
- **Piège** : `bin/console app:index:create` échoue si la collection `chunks` est vide au moment de la
  création de l'index (`Collection 'xxx.chunks' does not exist`). Il faut ingérer au moins un document
  avant de créer l'index — contrairement à un index Mongo classique, on ne peut pas créer l'index
  vectoriel "à vide" par avance.
- **Piège n°2, plus important** : `SchemaManager::waitForSearchIndexes()` a un timeout par défaut de
  10 secondes — bien trop court pour le backfill initial d'autoEmbed (Atlas doit embarquer chaque
  chunk existant via Voyage AI avant de marquer l'index `READY`). Avec 407 chunks, le timeout par
  défaut échouait systématiquement (`Timed out waiting for search indexes to become queryable after
  10000 ms`). Solution : ajout d'une option `--wait-ms` (défaut 300000 = 5 min) sur `app:index:create`.
  Avec ce délai, l'index passe `READY` sans souci. **Point d'article important** : le temps de
  disponibilité d'un index autoEmbed dépend directement du volume de contenu à embarquer au moment de
  la création — à anticiper dans un pipeline CI/CD (ne pas bloquer un déploiement sur un
  `waitForSearchIndexes` à délai court).

## 2026-07-02 18:44 — Refactoring demandé par Jérôme + piège `schema:drop`

Note : à partir d'ici, chaque entrée est horodatée (heure locale au moment de l'écriture), à la
demande de Jérôme. Les entrées précédentes n'ont que la date.

- **Remplacement du chunking maison par `symfony/ai-store`** : `TextSplitTransformer` (fenêtre de
  caractères fixe + overlap, `chunk_size=1500`, `overlap=225` ≈ 350 tokens / 15%) remplace notre
  `BlockPacker` fait main. Compromis assumé et documenté : c'est un découpage "aveugle" en
  caractères, il peut couper un paragraphe ou un bloc de code en plein milieu — on privilégie la
  réutilisation de l'outil fourni par l'écosystème Symfony AI plutôt qu'un splitter sémantique
  maison. Le découpage sémantique "en amont" (sections RST, symboles PHP via AST) est conservé car
  `symfony/ai-store` ne fournit pas d'équivalent ; seul le calibrage final en taille passe par
  `TextSplitTransformer`.
- **Propriétés publiques** : les classes `Document` (`Chunk`, `Issue`, `PullRequest`, `Comment`,
  `DocPage`, `CodeFile`) n'ont plus de getters — accès direct aux propriétés publiques. Conséquence :
  l'interface `ContentHashAware` a été supprimée (PHP ne permet pas de déclarer des propriétés dans
  une interface) ; `IngestionPipeline::ingest()` prend désormais un type union explicite
  (`Issue|PullRequest|Comment|DocPage|CodeFile`) et accède à `->id`/`->contentHash` directement.
- **xxh3 au lieu de sha1** : aucun des usages de hash dans ce projet n'est un enjeu de sécurité (ids
  de contenu déterministes pour l'idempotence de l'upsert, détection de changement de contenu) —
  remplacé partout par `hash('xxh3', ...)`, disponible nativement dans l'extension `hash` de PHP
  depuis 8.1, plus rapide qu'un hash cryptographique pour cet usage.
- **Piège important, non lié aux instructions mais découvert en nettoyant les anciens chunks** :
  après le changement de schéma d'id (sha1 → xxh3) et de stratégie de découpage, les 407 chunks déjà
  ingérés dans Atlas avec l'ancien schéma devenaient orphelins. Pour repartir propre, j'ai lancé
  `doctrine:mongodb:schema:drop --class="App\Document\Chunk" --class="App\Document\DocPage"`
  en pensant ne supprimer que ces deux collections — **la commande a en réalité supprimé toute la
  base `symfony`** (confirmé via `listDatabases()` : la base a disparu, il ne reste que
  `__mdb_internal_search`, `symfony_messenger`, `admin`, `local`). `schema:drop` avec `--class`
  filtre les index/collections supprimés mais exécute quand même un drop de la base entière à la fin.
  Sans conséquence ici (base de démo, aucune autre donnée), mais **point de vigilance sérieux pour
  l'article** : ne jamais lancer `doctrine:mongodb:schema:drop --class=...` sur une base partagée en
  pensant que `--class` limite la portée de la suppression — ce n'est pas le cas.

## 2026-07-02 20:40 — Ingestion complète du corpus EasyAdminBundle

Volume final ingéré sur `EasyCorp/EasyAdminBundle` :

| Source | Documents |
|---|---|
| Doc (`doc/*.rst`) | 42 pages |
| Code (`src/*.php`) | 308 fichiers |
| Issues | 3848 |
| Pull requests | 3827 |
| Commentaires | 20080 |
| **Chunks totaux** | **34022** |

- **Piège de timing** : le premier run d'`app:ingest:issues` était enveloppé dans un `timeout 900 ...
  | tail -100`. Le process a bien été tué par `timeout` au bout de 15 min (1120 issues traitées sur
  ~3850), mais comme la commande était pipée dans `tail`, le code de sortie rapporté par le shell
  était celui de `tail` (0), pas celui de `timeout` (124) — le run est apparu "terminé avec succès"
  alors qu'il avait été interrompu en plein milieu. **Point d'article/outillage** : ne jamais piper un
  process long dans `tail` sous un wrapper `timeout` si on veut détecter fiablement une interruption ;
  heureusement l'idempotence par `contentHash` a permis de simplement relancer la commande sans
  dupliquer ni re-payer l'embedding des 1120 premières issues déjà traitées.
- Volume réel très supérieur aux estimations initiales du plan (205 *issues ouvertes* annoncées par
  l'API repo, mais 3848 au total tous états confondus, sur l'historique complet du projet depuis sa
  création) — bon rappel que `open_issues_count` de l'API GitHub n'est pas du tout représentatif du
  volume total à ingérer si on inclut l'historique (`state=all`).
- Index `chunks_vector_idx` recréé sur les 34022 chunks avec un `--wait-ms=900000` (15 min) vu le
  volume nettement plus important que le test initial à 407 chunks.

## 2026-07-02 20:55 — Deux issues ouvertes sur doctrine/mongodb-odm

- [#3025](https://github.com/doctrine/mongodb-odm/issues/3025) : `schema:drop --class=X` supprime
  toute la base, pas seulement la collection de `X` (cf. section précédente) — demande de
  clarification de la doc ou de changement de comportement par défaut.
- [#3026](https://github.com/doctrine/mongodb-odm/issues/3026) : `odm:schema:create` n'expose aucune
  option pour attendre qu'un index search/vector passe `READY` (`SchemaManager::waitForSearchIndexes()`
  existe mais n'est appelé par aucune commande console). C'est exactement pour combler ce trou que
  `CreateVectorIndexCommand` existe dans ce projet — sans lui, `doctrine:mongodb:schema:create` suffit
  déjà à créer l'index (`createDocumentSearchIndexes` fait partie de son ordre de création par défaut),
  mais ne permet pas d'attendre la fin du backfill avant de continuer un script de déploiement/seed.

## 2026-07-02 21:15 — Page de recherche testée en conditions réelles : ça marche très bien

Test dans le navigateur (`symfony server:start`, page `/search`) avec la requête paraphrasée
**"how to hide a field only when creating a new entity, not when editing it"** — aucun mot-clé du
code ou du repo dans la requête. Résultat n°1 : la PR
[#5704](https://github.com/EasyCorp/EasyAdminBundle/pull/5704) *"hideWhenCreating() does not work
with useEntryCrudForm()"*, suivie immédiatement par l'issue
[#5696](https://github.com/EasyCorp/EasyAdminBundle/issues/5696) qu'elle corrige. **C'est LA
démonstration à mettre en avant dans l'article** : zéro recouvrement lexical entre la requête et
`hideWhenCreating()`, et pourtant le bon résultat sort en tête (score 0.5062). C'est exactement le
scénario où une recherche par mot-clé (GitHub Search) échouerait sans connaître le nom exact de la
méthode.

Test du filtre par type (`type=code_file`) sur la requête exacte `AbstractCrudController` : renvoie
en premier le chunk `class_doc` de la classe elle-même, puis `AbstractCrudTestCase` (sous-classe
liée) — le filtre `sourceType` sur l'index fonctionne comme attendu, et le chunk `class_doc` séparé
(cf. correctif du 2026-07-02 sur le chunking PHP) prouve son utilité ici : c'est lui qui remonte pour
une requête sur le nom de la classe, pas un chunk de méthode.

Aucune erreur en console navigateur. Le mélange de modèles Voyage (index `voyage-4`, requête
`voyage-4-lite`, exactement le pattern de l'exemple de la PR #3024) fonctionne bien en pratique sur
ces deux requêtes — à creuser plus systématiquement dans la comparaison de pertinence.

## 2026-07-02 21:20 — Piège : le rate-limit "search" de GitHub est distinct du rate-limit "core"

Premier run de `app:eval:compare` (17 requêtes × 2 appels GitHub) : resultats de code search
majoritairement "(no result)" à partir de la moitié de la liste, y compris pour des requêtes triviales
comme le nom de classe exact `AbstractCrudController` qui devrait pourtant ressortir immédiatement en
recherche plein texte. En creusant avec `gh api search/code`, confirmation : **rate limit atteint**
(`API rate limit exceeded`). `RateLimitGuard` (basé sur les headers `X-RateLimit-*` du endpoint "core",
5000 req/h) ne protège pas contre ça : GitHub applique une limite **secondaire séparée pour les
endpoints `/search/*`, beaucoup plus stricte (30 req/min authentifié)**, non exposée par les mêmes
headers, et notre `toArray(false)` avalait silencieusement l'erreur 403 en la traitant comme une
liste de résultats vide. **Deux bugs empilés** : pas de throttling adapté à `/search/*`, et une
erreur HTTP masquée en "aucun résultat" au lieu d'être remontée. Corrigé : `GitHubSearchService`
s'auto-throttle à un intervalle minimum de 2.5s entre appels (~24 req/min, sous la limite de 30), et
vérifie explicitement le status HTTP avant de décoder la réponse, avec une exception claire en cas
d'échec plutôt qu'un résultat vide silencieux. **Point d'article important** : comparer la pertinence
de deux moteurs de recherche suppose d'abord de vérifier qu'aucun des deux n'est en train d'échouer
silencieusement à cause d'un rate-limit — un biais facile à ne pas remarquer si le format de sortie
("aucun résultat") est indiscernable d'un vrai résultat vide.
