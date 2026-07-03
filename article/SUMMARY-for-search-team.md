# Automated embedding in the wild: a real corpus, real gotchas, real comparison

Internal summary for the vector-search/search team. Full write-up (more narrative, meant for an external blog post) in [`ARTICLE.md`](ARTICLE.md); raw logs of every step (including dead ends) in [`JOURNAL.md`](JOURNAL.md). Code: `easyadmin-vector-demo` on the `mongodb-autoembedding-demo` branch of this repo.

## What I built

A Symfony app that ingests a real, non-trivial corpus — all issues, PRs, comments, docs and source code from [EasyCorp/EasyAdminBundle](https://github.com/EasyCorp/EasyAdminBundle) (3848 issues, 3827 PRs, 20k comments, 308 PHP files, 42 doc pages → 34k chunks) — into MongoDB via Doctrine ODM, indexes it with `autoEmbed` (`voyage-4`), and exposes a search page + MCP server on top. On the same corpus, I also added a classic Atlas Search (Lucene) index and ran a keyword/GitHub Search comparison, then a third round putting an LLM (Claude Haiku) in the loop to reformulate queries and answer from results. This used the `autoEmbed` support added in [doctrine/mongodb-odm#3024](https://github.com/doctrine/mongodb-odm/pull/3024), merged into the `2.17.x` branch on 2026-07-03 (no tagged release yet, so the project still requires a dev-branch version, but directly from upstream now — no fork needed).

## Findings that matter for the product, not just for this demo

**Index creation on an empty collection fails.** `createDocumentSearchIndexes()` on a `Chunk` collection with zero documents throws `Collection 'x.chunks' does not exist`. Not necessarily wrong, but worth documenting explicitly for `autoEmbed` specifically, since "define the index before writing any data" is a very natural first instinct coming from regular MongoDB indexes.

**The initial backfill time scales with corpus size, and nothing in the tooling communicates that.** `SchemaManager::waitForSearchIndexes()`'s default 10s timeout is tuned for a normal index build, not for the embedding backfill `autoEmbed` triggers on every existing document. On 407 chunks it already timed out; on the full 34k-chunk corpus it took several minutes. There's no progress/ETA signal exposed anywhere (console output, index status fields) — you just poll until `READY`. Filed as a feature request: [doctrine/mongodb-odm#3026](https://github.com/doctrine/mongodb-odm/issues/3026) (expose `waitForSearchIndexes` via a CLI flag on `odm:schema:create`), now addressed by [PR #3028](https://github.com/doctrine/mongodb-odm/pull/3028) (adds `--wait` to `odm:schema:create`/`odm:schema:update`, not yet merged).

**No chunking guidance ships with the feature, and that's the actual hard part.** Everything else about `autoEmbed` is close to zero-config; deciding chunk size/overlap/boundaries is entirely on the developer, and it's where most of the implementation time went (semantic-boundary splitting for text, AST-based per-symbol chunking for code, a fixed-window fallback for unstructured content via `symfony/ai-store`'s `TextSplitTransformer`). Worth considering whether the product surface (docs, or even a helper) should say more than "pick a field."

**Data model constraint worth calling out more prominently in docs**: no array-of-embeddings support means "one chunk = one document" with a `parentId` back-reference is the only viable pattern once you have more than one logical chunk per source object. This is mentioned in passing in some internal docs I found via Glean but wasn't obvious from the public docs page alone.

**Unrelated but real footgun found along the way**: `doctrine:mongodb:schema:drop --class=X` drops the *entire database*, not just `X`'s collection — `--class` narrows which class's metadata resolves the target db/collection for each drop step, but the `DB` step in the default drop order still runs and MongoDB has no partial-database-drop primitive. Filed as [doctrine/mongodb-odm#3025](https://github.com/doctrine/mongodb-odm/issues/3025), fixed by [PR #3027](https://github.com/doctrine/mongodb-odm/pull/3027) (not yet merged): `--class` now excludes the `DB` step from the default drop order; `--db` must be passed explicitly to drop the whole database.

## Relevance comparison: Vector Search vs. classic Atlas Search (Lucene) vs. GitHub Search

Same corpus, same `content` field, two Atlas Search indexes (`autoEmbed` vs. classic full-text) side by side — this removes the "different corpus / different infra" confound that a comparison against GitHub Search inherently has.

- On **paraphrased/conceptual queries** (no exact vocabulary overlap with the corpus), Vector Search's Top-1 is topically relevant far more often than Lucene or GitHub Search, which frequently return nothing useful. Textbook case: *"how to hide a field only when creating a new entity, not when editing it"* → Vector Search's Top-1 is the exact PR fixing `hideWhenCreating()`, zero lexical overlap with the query.
- On **exact-symbol queries** ("control" queries: `AbstractCrudController`, `hideOnForm`, `BatchActionDto`...), Lucene did **not** reliably win despite that being its home turf: in all 5 control queries tested, our single-field Lucene index failed to surface the file that actually defines the symbol in Top-1 — outranked by higher-frequency mentions across hundreds of issue/comment chunks. This is a modeling choice on our part (one unweighted `content` field mixing all source types), not an inherent Lucene limitation, but it's a realistic failure mode worth flagging: naively porting a single-field full-text index from a homogeneous corpus to a mixed one (code + long-tail discussion) can quietly bias relevance toward volume over authority.
- **Latency**: Lucene 30-160ms vs. Vector Search 270-860ms on the same queries (`hrtime()`-measured, single-node, no query caching). Not a surprise, but a concrete number for anyone weighing hybrid search.
- Full table with links: `comparison.md` (generated by a reusable `app:eval:compare` command against the live indexes).

## Putting an LLM in the loop (Claude Haiku) changes the picture again

Rather than just comparing raw Top-1 links, I ran a second pass where a small model (1) answers cold (no retrieval) to check whether it already knows the answer, (2) reformulates the question per backend, (3) answers again using only the real search results.

- The "does it already know" check is not a formality: on well-documented symbols (`AbstractCrudController`) the cold answer is confident and correct; on internal/obscure ones (`configureFilters`, `BatchActionDto`) it honestly says "not sure." Any relevance eval that skips this check risks crediting retrieval for what's actually pretrained knowledge, or blaming retrieval for a question the model could never have answered anyway.
- **Once you look at the final grounded answer instead of the raw Top-1, no backend wins consistently — including on control queries.** Example: for `hideOnForm`, Lucene's (less "correct-looking") results let the model write a precise, working answer; Vector Search's results led to a vaguer one. For `configureFilters`, it's the reverse. The Top-1-link comparison above and the final-answer comparison here don't always agree — relevant if "relevance" is being evaluated anywhere as a proxy for downstream answer quality rather than measured on the answer itself.
- LLM query reformulation is not a free win: several GitHub Search reformulations invented plausible-but-invalid search qualifiers (`hideOnForm is:form`, `class:BatchActionDto`) that returned zero results where a plain keyword query likely would have worked.
- Full transcripts (all 17 questions, all reformulated queries, all answers): `llm-comparison.md`.

## Practical footguns unrelated to search relevance, in case useful to others

- Symfony's scoped `http_client` + a `base_uri` that itself contains a path: a request path with a leading `/` resolves from the origin per RFC 3986 and silently drops the base path's suffix (404, not a config error). Only matters if others build similar HTTP-gateway integrations the same way.
- GitHub's `/search/*` endpoints have a secondary abuse-detection limit well below the 30 req/min shown in `/rate_limit` — not relevant to MongoDB, but relevant if anyone benchmarks against GitHub Search again.

## Where to look

- Code: `easyadmin-vector-demo/` on branch `mongodb-autoembedding-demo`.
- `article/JOURNAL.md`: chronological, includes what didn't work and why.
- `article/ARTICLE.md`: external-facing write-up.
- `article/comparison.md`, `article/llm-comparison.md`: raw comparison data.
