# Technical decisions

Each decision is captured with the option taken and the trade-off accepted.

## 1. Normalized schema, not JSON columns

**Chosen:** four tables — `translation_keys`, `translation_values`, `tags`, `translation_tag` (pivot).

**Why:**
- Native indexing on `locale`, `content`, and tag names — every search filter hits a B-tree.
- Per-locale upsert is a single statement keyed on a unique index; mutating one locale inside a JSON blob would require read-modify-write.
- Tag membership is an indexed pivot lookup, not a JSON containment query.
- Schema is self-documenting and works the same in PostgreSQL, MySQL, and SQLite (test environment).

**Trade-off:** more tables and joins than a `key → {locales..., tags[]}` JSON document. Acceptable because the workload is read-heavy and indexed joins are exactly what relational engines are good at.

## 2. `upsert` for value writes

**Chosen:** `DB::table('translation_values')->upsert($rows, ['translation_key_id','locale'], ['content','updated_at'])`.

**Why:**
- One round-trip per write call regardless of how many locales are supplied.
- The unique index `(translation_key_id, locale)` is the conflict target — concurrency-safe, no `firstOrNew` race window.
- `PUT /api/translations/{key}` can both insert new locales and update existing ones in the same statement.

**Trade-off:** ties writes to the unique constraint. Removing it would break this path — that's the intended invariant.

## 3. Composite indexes

**Chosen:**

| Table | Index | Serves |
|---|---|---|
| `translation_keys` | `key` UNIQUE | single-key lookup |
| `translation_values` | `(translation_key_id, locale)` UNIQUE | export join, point lookup, integrity |
| `translation_values` | `locale` | per-locale export filter |
| `translation_tag` | `(translation_key_id, tag_id)` PK | "tags for this key" |
| `translation_tag` | `tag_id` | "keys with this tag" — used by search |
| `tags` | `name` UNIQUE | tag name resolution |

**Why:** every read path used by the API is served by exactly one index seek. The export touches only two indexes (the values composite and the keys PK).

**Trade-off:** writes pay slightly more index maintenance. Acceptable for a read-heavy workload.

## 4. Streaming export

**Chosen:** query builder + `cursor()` instead of loading all rows or returning a streamed HTTP response.

**Why:**
- Single streaming query — no repeated queries, no `OFFSET` performance cliff at 100k rows.
- `stdClass` rows skip Eloquent hydration cost (events, relations, mutators).
- Bounded memory: rows are fetched one at a time regardless of table size.
- The result is a plain PHP array, which is **cacheable**. A streaming HTTP response is not.
- lazyById was replaced with cursor because lazyById can generate multiple queries and degrade performance when used with joins.

**Trade-off:** the locale-keyed map is built in PHP. At very large scales (millions of rows) the array itself becomes the bottleneck. For the assignment's 100k target this is well below memory and time limits, and the cache layer eliminates repeated builds.

## 5. Database cache, not Redis

**Chosen:** `CACHE_STORE=database` with `Cache::remember()` and `Cache::flush()` on writes.

**Why:**
- No extra infrastructure to run, install, or document.
- Backed by a real persistent store (the `cache` table) — survives restarts.
- The 60-second TTL plus coarse `Cache::flush()` on writes is correct and easy to reason about.
- Switching to Redis is a single `.env` line — `CACHE_STORE=redis`. The application code is store-agnostic.

**Trade-off:** database cache reads are slower than Redis (~5–10ms vs <1ms) and add load to the same DB serving requests. For this scope and traffic profile that is comfortably under budget; for higher load Redis is the obvious upgrade.

## 6. Minimal test strategy

**Chosen:** one feature test file covering the four CRUD flows + one performance test, both backed by SQLite in-memory.

**Why:**
- Feature tests exercise the full HTTP → controller → service → repository → DB stack — high signal per test.
- SQLite in-memory means no external dependencies; CI runs the suite cold.
- One performance test guards the export SLA against regressions (assertion: under 1 second for ~1k rows; production target is sub-500ms via cache).
- Factories cover the three domain models; no mocks, no separate unit tests for code that the feature tests already cover.

**Trade-off:** SQLite's behavior diverges from PostgreSQL in edge cases (case sensitivity of `LIKE`, etc.). Acceptable for the flows under test; production behavior is verified against PostgreSQL.

**What is intentionally not tested:**
- Cache hit/miss timing — Laravel's cache is well-tested upstream.
- Sanctum token issuance internals — covered by the auth-required test on writes.
- Validation rules in isolation — exercised through the feature paths.

## 7. Bulk inserts in the seeder, not Eloquent factories

**Chosen:** `TranslationSeeder` uses `DB::table()->insert()` in 1,000-row batches instead of `TranslationKey::factory()->create()` per row.

**Why:**
- Eloquent factories perform one round-trip per row and fire model events. At 100k records that is 100k round-trips and minutes of run time.
- Bulk inserts: 100 round-trips total, no event overhead, completes in seconds.
- The `Tag` factory is still used for the small (10-row) tag pool — factory ergonomics are a clean win where the volume is low.

**Trade-off:** the bulk path bypasses model events. Seeder data is fixture data, not domain operations, so this is the correct trade-off.

---

Overall, the system prioritizes read performance, simplicity, and correctness over premature optimization.
