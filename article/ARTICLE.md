# L'auto-embedding MongoDB Atlas, mis à l'épreuve sur 34 000 morceaux d'EasyAdminBundle

Il y a deux choses qui reviennent systématiquement quand on parle de recherche sémantique avec un LLM : « il faut générer des embeddings » et « il faut bien découper son texte ». Les deux sont vraies, et les deux demandent normalement d'écrire et de maintenir un petit pipeline à part — appeler l'API d'un fournisseur d'embeddings, gérer les erreurs, réembarquer quand le contenu change, garder le modèle de requête synchronisé avec le modèle d'indexation.

MongoDB Atlas Vector Search propose depuis peu de faire disparaître cette étape : l'**automated embedding** (`autoEmbed`) laisse Atlas générer et maintenir les vecteurs à votre place, à l'indexation comme à la requête. Sur le papier c'est séduisant. En pratique ? Le seul moyen de savoir, c'est de brancher ça sur un vrai projet, avec du vrai contenu, et de voir ce qui casse.

Le projet : ingérer les issues, pull requests, commentaires, documentation et code source d'[EasyAdminBundle](https://github.com/EasyCorp/EasyAdminBundle) dans MongoDB, indexer le tout avec `autoEmbed`, exposer une recherche sémantique en Symfony (via [Doctrine MongoDB ODM](https://github.com/doctrine/mongodb-odm)) et un serveur MCP, puis comparer les résultats à un index Atlas Search classique (Lucene) sur le même corpus et à la recherche GitHub. Voici ce que ça a donné — y compris les deux bugs qu'on a fini par signaler en amont, depuis corrigés.

## Le terrain de jeu

EasyAdminBundle n'est pas un choix anodin : c'est un projet mature (plus de 3800 issues et autant de pull requests sur toute son histoire), avec une documentation fournie (42 pages RST) et une base de code de taille raisonnable (308 fichiers PHP). Assez gros pour être réaliste, assez petit pour tenir dans une démo d'article.

Le pipeline d'ingestion tourne en quatre commandes Symfony indépendantes et idempotentes (`app:ingest:doc`, `app:ingest:code`, `app:ingest:issues`, `app:ingest:pull-requests`) — idempotentes au sens où chaque document est identifié par un hash de son contenu, pas par un timestamp : rejouer l'ingestion ne recrée rien qui n'a pas changé, et donc ne repaie pas d'embedding pour du contenu déjà indexé. Volume final :

| Source | Volume |
|---|---|
| Documentation (`doc/*.rst`) | 42 pages |
| Code source (`src/*.php`) | 308 fichiers |
| Issues | 3848 |
| Pull requests | 3827 |
| Commentaires | 20 080 |
| **Chunks indexés** | **34 022** |

Petite surprise en cours de route : l'API GitHub affiche `open_issues_count: 205` pour ce repo. Le nombre réel d'issues *toutes périodes confondues* est 3848 — presque 19 fois plus. Si votre pipeline d'ingestion se base sur ce compteur pour dimensionner quoi que ce soit (temps estimé, coût d'embedding), corrigez le tir : il ne reflète que les issues ouvertes, pas l'historique.

## Découper le texte : la partie qu'on croit facile

La documentation MongoDB sur l'auto-embedding ne dit quasiment rien sur la stratégie de découpage — c'est vous qui décidez de la taille et de la forme des chunks avant qu'Atlas ne les embarque. Les recommandations qu'on a pu retrouver (documentation Voyage AI, retours d'expérience internes MongoDB) convergent sur quelques règles simples :

- des chunks de 200 à 512 tokens donnent les meilleurs résultats de récupération, mesurés empiriquement — ni trop courts (perte de contexte), ni trop longs (dilution du signal) ;
- un recouvrement de 15 à 20 % entre chunks consécutifs limite la perte d'information aux frontières ;
- découper le long de frontières sémantiques (paragraphes, sections, fonctions) plutôt qu'à taille fixe brutale, chaque fois que c'est possible ;
- pour du code : un chunk = une unité sémantique (une fonction, une méthode + son docblock), pas un découpage arbitraire par nombre de lignes.

Plutôt que de réinventer un splitter, on a utilisé celui fourni par l'écosystème Symfony AI : `Symfony\AI\Store\Document\Transformer\TextSplitTransformer`. C'est un découpage par fenêtre de caractères à taille fixe avec recouvrement — pas de conscience des paragraphes ni des blocs de code, juste une fenêtre glissante. Le compromis est assumé : on perd la finesse d'un découpeur sémantique maison, on gagne en cohérence avec l'outillage standard de l'écosystème. Pour le texte (issues, PR, commentaires, sections de doc RST), c'est direct : le contenu passe tel quel dans le splitter. Pour le code PHP, on garde une étape en amont : `nikic/php-parser` construit l'AST du fichier et en extrait les unités sémantiques (une méthode + son docblock, une classe entière si elle n'a pas de méthode) — c'est seulement le calibrage final en taille, si une méthode est démesurément longue, qui repasse par le splitter standard.

Un angle mort a été corrigé en cours de route : le docblock d'une classe (`/** Handles CRUD generation for admin controllers */`) contient souvent la description la plus utile pour la recherche sémantique — « à quoi sert ce contrôleur » — mais il était perdu dès que la classe avait au moins une méthode concrète (seul le corps des méthodes était chunké). Correctif : un petit chunk `class_doc` dédié est désormais toujours émis quand une classe a un docblock, en plus des chunks par méthode.

## Le schéma : un chunk, un document

Contrainte structurante d'`autoEmbed`, apprise en lisant le code source plutôt que la documentation : **impossible de stocker un tableau d'embeddings** dans un document. Le modèle de données ne peut donc pas être « une issue avec un tableau de chunks embarqués » — il faut une collection séparée où **un chunk = un document**, référençant son parent via un simple `parentId` :

```php
#[ODM\Document(collection: 'chunks')]
#[VectorSearchIndex(
    name: 'chunks_vector_idx',
    fields: [
        [
            'type' => 'autoEmbed',
            'path' => 'content',
            'modality' => 'text',
            'model' => 'voyage-4',
            'similarity' => 'dotProduct',
        ],
        ['type' => 'filter', 'path' => 'sourceType'],
        ['type' => 'filter', 'path' => 'parentId'],
    ],
)]
class Chunk
{
    public string $id;
    public string $parentId;
    public SourceType $sourceType;
    public string $content;   // <- le champ qu'Atlas embarque tout seul
    public int $chunkIndex;
    public array $metadata;
    public \DateTimeImmutable $indexedAt;
}
```

Ce support `autoEmbed` dans Doctrine MongoDB ODM était en cours de développement au moment de démarrer ce projet : il venait d'une [pull request](https://github.com/doctrine/mongodb-odm/pull/3024) alors non mergée, installée via un repository VCS Composer pointant sur la branche de la PR. Elle a depuis été mergée dans la branche `2.17.x` — pas encore de release taguée à l'heure où j'écris ces lignes, donc le projet requiert toujours une version de développement (`2.17.x-dev`), mais directement depuis le dépôt officiel, sans fork.

## Les pièges qu'on n'a trouvés qu'en pratique

Une bonne moitié du temps sur ce projet n'a rien à voir avec le code applicatif — c'est de la découverte de comportements non documentés (ou documentés ailleurs que là où on regarde). Florilège :

**On ne peut pas créer un index vectoriel sur une collection vide.** `Collection 'chunks' does not exist` — contrairement à un index Mongo classique, il faut au moins un document dans la collection avant de créer l'index `autoEmbed`. Ordre à respecter : ingérer, puis indexer.

**L'attente par défaut d'un index qui passe `READY` est bien trop courte.** `SchemaManager::waitForSearchIndexes()` a un timeout par défaut de 10 secondes. Pour un index `autoEmbed`, ce délai doit couvrir le *backfill initial* — Atlas doit embarquer chaque chunk existant via Voyage AI avant de déclarer l'index utilisable. Sur nos 34 022 chunks, il a fallu plusieurs minutes. Le temps de disponibilité dépend directement du volume à embarquer au moment de la création : à anticiper explicitement dans un pipeline CI/CD plutôt que de laisser un timeout par défaut faire échouer un déploiement.

**`doctrine:mongodb:schema:drop --class=X` ne fait pas ce que son nom suggère.** En voulant nettoyer uniquement la collection `chunks` après un changement de schéma d'ID, la commande a supprimé... toute la base de données. Ce n'est pas vraiment un bug — MongoDB ne permet pas de « dropper une base partiellement » — mais le comportement par défaut de la commande (aucune option `--collection`/`--index`/`--search-index` ne restreint le périmètre) exécute quand même toutes les étapes de suppression, y compris celle au niveau base entière, même quand on pense n'avoir ciblé qu'une classe. [Issue ouverte](https://github.com/doctrine/mongodb-odm/issues/3025) pour clarifier ou changer ce défaut. Sans conséquence ici (base de démo), mais à connaître avant de lancer cette commande sur une base partagée.

**`doctrine:mongodb:schema:create` sait déjà créer les index `autoEmbed`** — pas besoin d'une commande maison pour ça, `createDocumentSearchIndexes()` fait partie de son comportement par défaut. Ce qui manque en revanche, c'est un moyen d'attendre que l'index soit prêt : `waitForSearchIndexes()` existe dans le SDK mais n'est exposé par aucune commande console. [Deuxième issue ouverte](https://github.com/doctrine/mongodb-odm/issues/3026) pour demander un `--wait`/`--wait-ms` sur la commande standard — plus pertinent que jamais avec `autoEmbed`, puisque le temps de build dépend désormais du débit du fournisseur d'embeddings, pas juste de la taille de l'index.

**Le rate-limit de recherche GitHub n'a rien à voir avec le rate-limit "normal".** Pour comparer équitablement Vector Search et GitHub Search, il fallait interroger `/search/issues` et `/search/code`. Premier run : la moitié des résultats de recherche de code revenaient vides — y compris pour des requêtes triviales comme le nom exact d'une classe. En creusant : rate limit atteint, silencieusement avalé par notre code qui traitait une erreur HTTP 403 comme une liste de résultats vide. Pire, `/rate_limit` continuait d'afficher un quota `search` intact (30/30) : la limite qui nous frappait était une protection anti-abus distincte, non documentée dans les mêmes headers. Correctif : auto-throttling à ~3 secondes entre appels, et une erreur explicite plutôt qu'un silence qui ressemble à un vrai résultat vide. **Une leçon qui dépasse ce projet** : avant de comparer la pertinence de deux moteurs de recherche, s'assurer qu'aucun des deux n'échoue en silence à cause d'un rate-limit — le biais est facile à rater si un résultat vide et un échec masqué se ressemblent trait pour trait dans la sortie.

## Est-ce que ça marche vraiment ?

Voici le test qui vaut tous les benchmarks : une requête en langage naturel, sans aucun mot-clé du code.

> **Requête** : *"how to hide a field only when creating a new entity, not when editing it"*

Résultat n°1 de la recherche vectorielle : la pull request [**"hideWhenCreating() does not work with useEntryCrudForm()"**](https://github.com/EasyCorp/EasyAdminBundle/pull/5704), qui corrige exactement l'issue [#5696](https://github.com/EasyCorp/EasyAdminBundle/issues/5696) décrivant ce même besoin. Aucun mot de la requête — « hide », « creating », « editing » — n'apparaît littéralement dans `hideWhenCreating()`. C'est précisément le scénario où une recherche par mot-clé échoue sans connaître le nom exact de la méthode, et où l'auto-embedding tient sa promesse.

Pour objectiver un peu plus, la page de recherche interroge l'index avec `voyage-4-lite` alors que l'indexation a été faite avec `voyage-4` — exactement le couple utilisé dans l'exemple de la PR Doctrine ODM. Rien ne garantit *a priori* que deux modèles Voyage différents produisent des vecteurs comparables (dimensions et distributions distinctes selon le modèle). Sur nos tests, le mélange fonctionne : les résultats en tête restent pertinents. Prendre ça comme une validation empirique limitée à ce couple de modèles précis, pas comme une garantie générale — c'est le genre de détail à revérifier soi-même avant de le reproduire en production.

Même requête, index Atlas Search classique (Lucene) cette fois, sur le même champ `content` et le même corpus : premier résultat, une issue intitulée *"CollectionField bug with useEntryCrudFrom??"* — liée au même sujet en surface (elle parle bien de champs qui se comportent différemment en création et en édition), mais ce n'est pas la bonne issue, et le nom exact de la méthode en cause (`hideWhenCreating`) n'apparaît nulle part dans ce résultat. Le score Lucene (12.8, basé sur la fréquence des mots comme « creating », « editing », « field ») a favorisé un texte qui répète ces mots courants, pas celui qui répond réellement à la question.

## Vector Search vs Lucene vs GitHub Search, sur 17 requêtes

Trois catégories de requêtes ont été testées : des **reformulations** sans le vocabulaire exact du code, des questions **conceptuelles** transversales (censées faire remonter à la fois de la doc, du code et des issues), et des requêtes **contrôle** utilisant un nom de symbole exact — le terrain où la recherche par mot-clé est censée exceller. Le comparatif inclut maintenant trois systèmes : la recherche vectorielle (`autoEmbed`), un index Atlas Search classique posé sur le même champ `content` de la même collection (pour comparer à armes égales, sans confondre "moteur différent" et "corpus différent"), et GitHub Search comme référence externe.

| Requête (extrait) | Type | Top-1 Vector Search | Top-1 Lucene | Top-1 GitHub Issues |
|---|---|---|---|---|
| *hide a field only when creating* | reformulation | PR exacte sur le sujet | Issue liée mais différente | Question tangentielle |
| *conditionally show a field based on another* | reformulation | Discussion exacte sur les champs conditionnels | Discussion tangentielle | Aucun résultat |
| `AbstractCrudController` | contrôle | Mentionne la classe | Commentaire tangentiel | Trouve la classe |
| `hideOnForm` | contrôle | Trouve la méthode exacte | Issue sans rapport | Trouve le fichier exact |
| `BatchActionDto` | contrôle | PR sur la classe | Commentaire tangentiel | Trouve le fichier exact |

(Table complète avec 17 requêtes, latences et liens dans `comparison.md` — générée par `app:eval:compare`, rejouable à volonté.)

Le résultat le plus contre-intuitif de ce comparatif ne concerne pas les requêtes reformulées — sur celles-là, la recherche vectorielle confirme son avance, Lucene se comportant en gros comme GitHub Search (des résultats corrects quand le vocabulaire correspond, à côté de la plaque sinon). Ce qui surprend, c'est le comportement de **Lucene sur les requêtes contrôle** : sur les cinq requêtes à nom de symbole exact (`AbstractCrudController`, `hideOnForm`, `BatchActionDto`, `configureFilters`, `isSetOnEditPageMethod`), notre index Lucene ne retrouve **le fichier de code qui définit réellement le symbole en premier résultat dans aucun des cinq cas** — il remonte systématiquement une issue ou un commentaire qui mentionne le terme au passage. Vector Search, lui, retrouve le bon fichier ou une pull request très pertinente dans trois cas sur cinq.

Ce n'est pas un bug, c'est une conséquence directe d'un choix de modélisation qu'on a fait sans trop y réfléchir au départ : un seul champ `content` mélangeant issues, commentaires, pull requests, doc et code, sans pondération par type de source. `AbstractCrudController` apparaît une fois dans le fichier qui le définit, mais des dizaines de fois dispersées dans des centaines de commentaires qui en parlent — et le score de pertinence d'un index plein-texte classique (une variante de BM25) récompense la fréquence du terme dans le corpus, pas le fait d'être *la* source qui fait autorité. La recherche vectorielle, elle, capture une notion de proximité sémantique globale du chunk à la requête, moins sensible à ce biais de volume.

Sur la latence, en revanche, Lucene écrase largement Vector Search : entre 30 et 160 ms de moyenne contre 270 à 860 ms pour la recherche vectorielle sur les mêmes requêtes. Ce n'est pas gratuit d'aller chercher un sens plutôt que des mots.

Une différence structurelle mérite enfin d'être soulignée au-delà des scores et des temps de réponse : la recherche vectorielle (comme Lucene, d'ailleurs, puisque c'est le même principe d'index Atlas Search) mélange nativement issues, pull requests, documentation et code dans un seul jeu de résultats classés par pertinence. Reproduire ça côté GitHub demande d'interroger deux endpoints séparés (`/search/issues`, `/search/code` — sans compter que GitHub ne propose même pas d'endpoint de recherche pour le contenu des wikis) et de fusionner les résultats à la main, sans notion de score commun entre les deux.

## Et si on laisse un LLM faire la recherche à notre place ?

Tout ce qui précède compare des résultats bruts — un Top-1, un lien. Mais dans un usage réel (RAG, agent, MCP), personne ne lit une liste de liens : un modèle reformule la question, cherche, puis répond en s'appuyant sur ce qu'il a trouvé. On a rejoué exactement ce scénario avec un modèle simple (Claude Haiku), sur les 17 mêmes requêtes, pour chacun des trois systèmes : (1) une réponse "à froid", sans aucune recherche — pour vérifier que le modèle ne connaît pas déjà la réponse par cœur —, (2) une reformulation de la question adaptée au moteur ciblé, (3) une réponse finale construite uniquement à partir des résultats obtenus.

Premier constat, avant même de comparer les moteurs : le garde-fou "le modèle connaît-il déjà la réponse" n'est pas un détail. Sur `AbstractCrudController` ou sur *"hide a field only when creating"*, la réponse à froid de Haiku est déjà confiante et correcte — il connaît `hideWhenCreating()` par son nom. Mais sur des symboles moins exposés publiquement (`configureFilters`, `hideOnForm`, `BatchActionDto`, `isSetOnEditPageMethod`), il répond honnêtement "je ne suis pas sûr, peux-tu préciser ?". Sans ce test préalable, on aurait pu attribuer à tort la qualité d'une réponse à la recherche plutôt qu'à la mémoire du modèle.

Deuxième constat, plus surprenant : une fois qu'on regarde la réponse *finale* plutôt que le Top-1 brut, plus aucun système ne gagne systématiquement — y compris sur les requêtes "contrôle" où Lucene semblait nettement distancé dans la comparaison précédente. Sur `hideOnForm`, le contexte trouvé par Lucene (des issues qui *utilisent* la méthode dans des extraits de code) a permis à Haiku de rédiger une réponse précise avec un exemple fonctionnel ; le contexte de Vector Search (une discussion sur des conventions de nommage) a produit une réponse qui tourne autour du sujet sans jamais l'expliquer. Sur `configureFilters`, c'est l'inverse : Vector Search retrouve directement les deux signatures de la méthode dans le code source et produit la meilleure réponse des trois. Ce n'est pas la source qui fait la différence, c'est ce que le chunk retrouvé permet concrètement d'écrire.

Le cas le plus net en faveur de la recherche vectorielle reste *"customize the text shown on the save button"* : seul Vector Search retrouve la discussion (une pull request fermée en "won't merge") expliquant que le bouton "Save" est **volontairement** non personnalisable, pour éviter d'introduire un cas spécial dans la base de code. Lucene et GitHub Search ne trouvent rien d'exploitable sur la même question, et Haiku le dit honnêtement plutôt que d'inventer une réponse.

Dernier enseignement, à charge cette fois contre l'idée qu'un modèle "intelligent" reformule forcément mieux qu'un humain pressé : plusieurs requêtes contrôle voient Haiku inventer une syntaxe de recherche GitHub plausible mais inexistante (`hideOnForm is:form`, `class:BatchActionDto`) qui renvoie zéro résultat — une requête mot-clé toute simple aurait sans doute mieux marché. Et dans les deux tests manuels antérieurs à ce comparatif automatisé, la reformulation de Haiku pour Vector Search a parfois *dégradé* la recherche par rapport à la question originale en langage naturel. Reformuler n'est pas un gain automatique, y compris pour le moteur censé le mieux comprendre le langage naturel.

Ce qui rassure malgré tout : dans les 51 réponses "avec contexte" générées (17 requêtes × 3 systèmes), le modèle ne s'est jamais appuyé en douce sur sa connaissance a priori quand elle n'apparaissait pas dans les résultats fournis — il dit explicitement "je ne trouve pas cette information ici" plutôt que de mélanger les deux sources. C'est exactement la discipline qu'on attend d'un système RAG en production : une réponse traçable à ses sources, pas une réponse qui a l'air bonne.

(Rapport complet, question par question, avec les réponses intégrales et les requêtes reformulées : `article/llm-comparison.md`, généré par `app:eval:llm-compare`.)

## Ce qu'on en retient

L'auto-embedding tient sa promesse : le code applicatif n'a jamais eu à appeler une API d'embedding, ni à gérer une clé Voyage AI, ni à se soucier de resynchroniser quoi que ce soit — un champ `autoEmbed` dans la définition de l'index, et Atlas s'occupe du reste, aussi bien à l'écriture qu'à la lecture. Ce qui reste entièrement à la charge du développeur, et que la fonctionnalité ne prétend pas résoudre, c'est tout ce qui touche à la *forme* du contenu avant qu'il n'arrive dans le champ embarqué : la stratégie de découpage, le modèle de données qui en découle (un chunk = un document, pas un tableau), et la discipline d'idempotence pour ne pas payer deux fois le même embedding.

Deux issues ouvertes en cours de route ([#3025](https://github.com/doctrine/mongodb-odm/issues/3025) sur `schema:drop`, [#3026](https://github.com/doctrine/mongodb-odm/issues/3026) sur l'attente d'un index prêt) montrent aussi que l'outillage autour de cette fonctionnalité encore jeune avait de la marge de progression — rien de bloquant, mais de quoi affiner l'expérience des prochains qui s'y frotteront. Les deux ont depuis leur propre PR de correctif ([#3027](https://github.com/doctrine/mongodb-odm/pull/3027) et [#3028](https://github.com/doctrine/mongodb-odm/pull/3028)), pas encore mergées à l'heure où j'écris ces lignes.

Le comparatif à trois — vectoriel, Lucene, GitHub — suggère aussi une évidence qu'on oublie facilement en testant un seul système à la fois : vectoriel et lexical ne sont pas deux candidats pour la même place, ce sont deux outils complémentaires. Lucene est nettement plus rapide et reste imbattable quand l'utilisateur connaît déjà le terme exact qu'il cherche *et* que ce terme est rare dans le corpus ; la recherche vectorielle gagne dès que la formulation s'éloigne du vocabulaire du code, mais paie ça en latence et peut se faire distancer par Lucene même sur du contrôle si le mot cherché est très fréquent ailleurs dans le corpus (notre cas `AbstractCrudController`). La suite logique — hors périmètre de cette démo, mais la piste la plus évidente — serait une recherche hybride : un filtre Lucene rapide en amont, ou une fusion pondérée des deux scores, plutôt que de choisir l'un contre l'autre.

---

*Projet complet : ingestion, chunking, index, recherche web et serveur MCP dans `easyadmin-vector-demo/`. Journal détaillé de toutes les étapes (succès et échecs) dans `JOURNAL.md`.*
