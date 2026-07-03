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

## 2026-07-02 21:56 — Ajout d'un index Lucene classique pour une comparaison à armes égales

Demande de Jérôme : comparer aussi à un index Atlas Search classique (Lucene), sur le **même**
corpus MongoDB — plus rigoureux que la comparaison à GitHub Search, qui reposait sur un tout autre
moteur, un tout autre découpage du contenu, et une infrastructure hors de notre contrôle.

- Ajout d'un second attribut `#[SearchIndex(name: 'chunks_lucene_idx', fields: [...])]` sur `Chunk`,
  à côté de `#[VectorSearchIndex]` — les deux coexistent sans conflit (ce sont deux index distincts,
  pas un mélange de types `vector`/`autoEmbed` dans le même index, ce qui aurait été refusé).
  `content` en `type: string` (texte analysé), `sourceType`/`parentId` en `type: token` (égalité
  exacte, pour le filtrage). **Bonne surprise** : `app:index:create` n'a nécessité aucune
  modification — `SchemaManager::createDocumentSearchIndexes()` récupère et crée déjà tous les index
  de recherche définis sur la classe (vector *et* classique) en une seule fois, confirmé en lisant
  `ClassMetadata::getSearchIndexes()`/`prepareSearchIndexes()`.
- `LexicalSearchService` interroge ce nouvel index via le stage d'agrégation `$search` + opérateur
  `text()` (ou `compound()` avec `must()->text()` + `filter()->equals()` quand un `sourceType` est
  précisé). API découverte par lecture directe du SDK (`Aggregation/Stage/Search.php` et
  `Search/SupportsAllSearchOperatorsTrait.php`) plutôt que devinée.
- Page de recherche web : sélecteur "Vector Search / Lucene full-text" ajouté, le champ modèle Voyage
  se désactive automatiquement en mode Lucene.

### Résultat le plus intéressant : Lucene ne gagne pas toujours sur les requêtes "contrôle"

Intuition de départ : sur une requête à mot-clé exact (`AbstractCrudController`, `hideOnForm`,
`BatchActionDto`...), un index plein-texte classique devrait trivialement gagner. **Ce n'est pas ce
qu'on observe.** Sur ces 5 requêtes contrôle, Lucene ne retrouve le fichier de code qui définit
réellement le symbole dans **aucun** cas en premier résultat — il remonte systématiquement un
commentaire ou une issue qui mentionne le terme, alors que Vector Search trouve le bon fichier ou une
pull request très pertinente 3 fois sur 5. Explication la plus probable : notre index Lucene porte
sur un unique champ `content` mélangeant tous les types de chunks (issues, commentaires, PR, doc,
code) sans pondération par type ni par nombre d'occurrences relatives — un nom de classe très discuté
dans des dizaines d'issues/commentaires génère plus de correspondances "en fréquence" que la poignée
de chunks de code qui le définissent réellement, et le score `searchScore` de MongoDB (BM25-like)
récompense la fréquence du terme dans le corpus, pas le fait d'être la source canonique. **Ce n'est
pas un bug, c'est un choix de modélisation** (un seul champ texte, pas de pondération par
`sourceType`) qui mérite d'être documenté comme limite plutôt que comme échec.

Sur les requêtes reformulées/conceptuelles (sans le vocabulaire exact), Lucene échoue au moins aussi
souvent que GitHub Search à trouver un résultat pertinent — confirme sur la même infrastructure ce
qu'on avait déjà vu face à GitHub, en éliminant cette fois l'hypothèse "peut-être que l'algorithme de
GitHub est juste différent/moins bon".

**Latence, en revanche, nettement à l'avantage de Lucene** : 30-160 ms contre 270-860 ms pour Vector
Search sur les mêmes requêtes (mesuré via `microtime()` dans `app:eval:compare`). Point à mettre en
avant dans l'article : la recherche vectorielle n'est pas gratuite en latence, et une architecture de
recherche hybride (Lucene en pré-filtre rapide + rerank vectoriel, ou l'inverse) serait la suite
logique — hors du périmètre de cette démo, mais une piste concrète à mentionner en conclusion.

## 2026-07-02 22:05 — microtime() remplacé par hrtime()

Demande de Jérôme : toujours utiliser `hrtime(true)` plutôt que `microtime(true)` pour mesurer des
durées. Raison implicite (non précisée mais logique) : `hrtime()` s'appuie sur une horloge monotone
(`CLOCK_MONOTONIC`), insensible aux ajustements de l'horloge système (NTP, changement d'heure), et
retourne un entier en nanosecondes plutôt qu'un flottant en secondes — plus précis et sans les pièges
d'arithmétique flottante sur des mesures de latence. `microtime(true)` reste correct pour un
timestamp absolu, mais ne devrait jamais servir à calculer un `$fin - $début`.

Deux usages corrigés : le throttle de `GitHubSearchService` (intervalle minimum entre appels,
désormais en nanosecondes) et les mesures de latence de `app:eval:compare` (Vector/Lucene/GitHub).

## 2026-07-02 22:25 — Un LLM dans la boucle (Claude Haiku via Grove)

Demande de Jérôme : introduire un modèle simple (Haiku) qui reformule la question pour chaque moteur,
exécute la vraie recherche, puis répond en ne s'appuyant que sur les résultats — plus proche d'un
usage RAG réel qu'un simple relevé de Top-1. Avec un garde-fou explicite : vérifier si le modèle
connaît déjà la réponse sans aucun contexte, ce qui rendrait la comparaison RAG peu concluante pour
cette requête précise.

- **Rejet du plan initial** : j'avais prévu d'utiliser le bridge `symfony/ai-anthropic-platform`
  (cohérent avec l'usage de `symfony/ai-store` plus tôt). Jérôme a corrigé : il dispose d'un proxy
  interne "Grove" (`GROVE_API_KEY`/`GROVE_API_URL`, déjà dans `.env.local`) exposant une API
  compatible Anthropic Messages, avec un header `api-key` (pas `x-api-key`). Test demandé et fait
  avant d'écrire du code : script PHP jetable chargeant `.env.local` via le `Dotenv` de Symfony,
  deux appels curl équivalents. Résultat : `api-key` → HTTP 200 (réponse Anthropic normale,
  `claude-haiku-4-5-20251001`) ; `x-api-key` → HTTP 401 "missing subscription key" (signature typique
  d'une gateway Azure API Management). Le bridge officiel, qui code en dur `x-api-key`, n'aurait donc
  pas fonctionné contre Grove — **vérifié avant d'écrire le code, pas supposé après coup**. Décision :
  appel HTTP direct via `symfony/http-client` (`grove.client`, même pattern que `github.client`), pas
  de nouvelle dépendance Composer.
- **Piège de résolution d'URL** : `base_uri: '%env(GROVE_API_URL)%/anthropic'` + requête sur
  `/v1/messages` (slash initial) a silencieusement perdu le segment `/anthropic` — la requête réelle
  est partie sur `https://grove-gateway-prod.azure-api.net/v1/messages` (404). Cause : la résolution
  d'URL RFC 3986 traite un chemin de requête commençant par `/` comme *absolu depuis l'origine*
  (schéma + hôte), pas relatif au chemin du `base_uri`. Correctif : `base_uri` avec slash final
  (`.../anthropic/`) et chemin de requête **sans** slash initial (`v1/messages`) pour une résolution
  relative correcte. **Point d'article** : avec les clients HTTP scopés Symfony, un `base_uri` qui
  contient lui-même un chemin (pas juste un host) est fragile si on ne fait pas attention au slash
  initial des requêtes — ça marche silencieusement mal (404, pas d'erreur de config) plutôt que
  d'échouer bruyamment.
- **Résultat qualitatif immédiat, sur la toute première requête testée** ("how to hide a field only
  when creating...") : la réponse à froid de Haiku (sans aucun contexte) cite déjà `hideWhenCreating()`
  par son nom exact et donne un exemple de code correct. Le modèle connaît vraisemblablement déjà ce
  bout de documentation/code EasyAdminBundle par pré-entraînement. Fait intéressant : dans les 3
  réponses "avec contexte" (Vector/Lucene/GitHub), le modèle ne réutilise **pas** cette connaissance
  a priori et répond honnêtement "je ne trouve pas cette information dans les résultats fournis" —
  aucun des trois moteurs n'a remonté `hideWhenCreating()` dans le top-3 avec la requête reformulée
  par Haiku (différente de la requête littérale utilisée dans les tests manuels précédents).
  **Deux enseignements en un** : (1) le garde-fou "connaît-il déjà la réponse" est loin d'être
  anecdotique — sur au moins cette requête, tester le RAG en aveugle aurait pu laisser croire que
  "aucun moteur ne trouve la bonne réponse" alors que le modèle la connaissait très bien par ailleurs;
  (2) la reformulation de la requête par le modèle n'est pas toujours un gain : une requête
  reformulée en mots-clés génériques ("field visibility", "conditional field") peut être moins
  efficace que la question originale en langage naturel, y compris pour la recherche vectorielle.

## 2026-07-02 22:39 — Résultats de la comparaison LLM sur les 17 requêtes

Rapport complet dans `article/llm-comparison.md`. Lecture des 17 blocs, enseignements principaux :

- **Le garde-fou "connaît-il déjà la réponse" était loin d'être anecdotique.** Sur les requêtes
  "contrôle" portant sur des symboles peu documentés publiquement (`isSetOnEditPageMethod`,
  `configureFilters`, `hideOnForm`, `BatchActionDto`), Haiku répond honnêtement "je ne suis pas sûr,
  peux-tu préciser ?" en réponse à froid — pas de connaissance a priori. Mais sur `AbstractCrudController`
  (classe très documentée/discutée) et sur la requête `hideWhenCreating`, la réponse à froid est
  confiante et globalement correcte. Le modèle sait distinguer ce qu'il sait de ce qu'il ne sait pas,
  ce qui valide la méthodologie plutôt que de la fragiliser.
- **Aucun système ne gagne systématiquement sur les requêtes "contrôle"** — nuance par rapport à la
  comparaison brute (Top-1) faite précédemment. Exemple `hideOnForm` : le contexte trouvé par Lucene
  (des issues qui *utilisent* la méthode dans du code cité) a permis à Haiku de produire une réponse
  précise avec exemples de code corrects ; le contexte trouvé par Vector Search (une discussion sur
  les conventions de nommage) a produit une réponse vague, à côté du sujet. Sur `configureFilters` en
  revanche, Vector Search a trouvé directement les deux signatures de méthode (dans
  `AbstractDashboardController` et `AbstractCrudController`) et a produit la meilleure réponse des
  trois systèmes. **Ce n'est pas la source qui compte le plus, c'est ce que le chunk retrouvé permet
  concrètement de dire.**
- **Meilleur exemple de valeur ajoutée nette du RAG** : "customize the text shown on the save button".
  Seul Vector Search a retrouvé le contexte permettant à Haiku de répondre avec précision : le bouton
  "Save" est *volontairement non personnalisable* (hardcodé), une PR proposant cette fonctionnalité a
  été fermée en "won't merge" pour éviter un cas spécial dans la codebase. Lucene et GitHub Search
  n'ont rien trouvé d'utile sur cette même requête — leurs réponses se limitent à "je ne sais pas".
  Un exemple concret où retrouver la bonne discussion (même vieille, même en commentaire) change
  complètement la qualité de la réponse.
- **La reformulation par le modèle n'est pas toujours un service rendu, surtout pour GitHub.** Sur
  plusieurs requêtes contrôle, Haiku invente une syntaxe de recherche GitHub plausible mais fausse
  (`hideOnForm is:form`, `class:BatchActionDto`, `isSetOnEditPageMethod` seul) qui renvoie zéro
  résultat, alors qu'une requête mot-clé simple aurait probablement mieux fonctionné. Une reformulation
  "trop maligne" peut nuire à la recherche autant qu'elle peut l'aider — vrai aussi une fois pour
  Vector Search sur la toute première requête testée (cf. entrée précédente).
- **Discipline de grounding respectée dans tous les cas observés** : même quand la réponse à froid
  contenait déjà la bonne information, aucune des 3 réponses "avec contexte" ne l'a réutilisée en
  douce si elle n'apparaissait pas dans les résultats fournis — le modèle dit explicitement "je ne
  trouve pas cette information dans les résultats" plutôt que de mélanger connaissance a priori et
  contexte récupéré. Comportement rassurant pour un usage RAG réel, où on veut justement que la
  réponse soit traçable aux sources citées.

Cases "Pertinent ?" et "connaissait-il déjà ?" du rapport laissées vides pour jugement par Jérôme —
17 requêtes × 3 systèmes + 17 réponses à froid, volume trop important pour une évaluation automatique
fiable sans vérité terrain.

## 2026-07-03 15:52 — PR #3024 mergée, deux PR de correctifs ouvertes

- [doctrine/mongodb-odm#3024](https://github.com/doctrine/mongodb-odm/pull/3024) (support `autoEmbed`)
  a été mergée dans la branche `2.17.x` (commit `2800819`). Plus besoin du fork
  `GromNaN/mongodb-odm` : `composer.json` mis à jour pour requérir directement
  `doctrine/mongodb-odm: 2.17.x-dev` depuis le dépôt officiel, `repositories` (VCS) supprimé.
  **Piège composer noté au passage** : pour une branche nommée `2.17.x` (motif de version), la
  contrainte à utiliser est `2.17.x-dev`, pas `dev-2.17.x` — cette dernière syntaxe échoue
  ("does not match the constraint") car composer a déjà calculé l'alias de version de la branche
  à partir de son nom.
- Les deux issues remontées pendant cette démo ont chacune leur PR de correctif, ouvertes par
  Jérôme :
  - [#3025](https://github.com/doctrine/mongodb-odm/issues/3025) (schema:drop supprime toute la
    base) → corrigée par [#3027](https://github.com/doctrine/mongodb-odm/pull/3027) : `--class`
    exclut désormais l'étape `DB` de l'ordre de suppression par défaut ; il faut passer `--db`
    explicitement pour supprimer la base entière.
  - [#3026](https://github.com/doctrine/mongodb-odm/issues/3026) (pas d'attente possible sur
    `schema:create`) → corrigée par [#3028](https://github.com/doctrine/mongodb-odm/pull/3028) :
    ajout d'une option `--wait` (avec valeur optionnelle en durée `strtotime()` ou millisecondes) sur
    `odm:schema:create` et `odm:schema:update`, branchée sur `SchemaManager::waitForSearchIndexes()`.
    Une fois cette PR mergée, `CreateVectorIndexCommand` de ce projet devient un simple wrapper
    redondant avec `doctrine:mongodb:schema:create --search-index --wait=...` — à retirer à ce
    moment-là plutôt que maintenu en double.
- `bin/console doctrine:mongodb:mapping:info` toujours vert après la mise à jour ; pas de changement
  d'API entre la branche du fork et la version mergée (attribut `#[VectorSearchIndex]` identique).
