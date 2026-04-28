# Architecture

## Layered design

```
HTTP Request
    │
    ▼
Controller        ← parses HTTP, validates via FormRequest, formats response
    │
    ▼
Service           ← orchestrates use cases, owns transaction boundaries
    │
    ▼
Repository        ← isolates persistence (Eloquent + query builder)
    │
    ▼
Model / DB
```

Each layer has a single responsibility:

- **Controller** — HTTP-only concerns. No business logic, no direct DB calls. Reads `FormRequest` data, calls a service method, formats the response, applies cache (export only).
- **Service** — `TranslationService` composes repository calls into business operations (create, update, search, export). Owns DB transactions for multi-step writes.
- **Repository** — `TranslationRepository` and `TagRepository` encapsulate persistence. Eloquent for write paths, query builder for the hot read path (export).
- **Model** — `TranslationKey`, `TranslationValue`, `Tag` define relationships and fillable fields. No query scopes or business logic.

Cache is consulted only at the controller boundary for the export endpoint — it never hides inside the service or repository.

## Data model

```
translation_keys            tags
   id (PK)                    id (PK)
   key (UNIQUE)               name (UNIQUE)
        │                         │
        │ 1                       │ N
        │                         │
        ▼                         │
translation_values    translation_tag (pivot, composite PK)
   id (PK)              translation_key_id ──┐
   translation_key_id   tag_id ──────────────┘
   locale
   content
   UNIQUE (translation_key_id, locale)
   INDEX  (locale)
```

Translation values and tags live in normalized tables. Indexes were chosen to serve specific access patterns — see `DECISIONS.md`.

## Export flow

The export endpoint (`GET /api/translations/export`) is the most demanding read path. It returns every translation grouped by locale:

```json
{ "en": { "key": "value" }, "fr": { "key": "value" } }
```

### Request flow

```
GET /api/translations/export?locale=en
        │
        ▼
TranslationController::export
        │ Cache::remember('translations.export.en', 60, …)
        ▼  (miss only)
TranslationService::export
        │ foreach (streamForExport(locale) as $row)
        │     $map[$row->locale][$row->key] = $row->content;
        ▼
TranslationRepository::streamForExport
        │ DB::table('translation_values as tv')
        │   ->join('translation_keys as tk', …)
        │   ->select('tk.key', 'tv.locale', 'tv.content')
        │   ->orderBy('tv.id')
        │   ->lazyById(2000, 'tv.id', 'id')
        ▼
PostgreSQL — single indexed JOIN, keyset cursor
```

### Why streaming

A naïve implementation loads all rows into memory before transforming. At 100k rows that materializes:

- ~100k Eloquent model instances (heavy: events, relations, mutators).
- A second collection of nested arrays for grouping.
- Peak memory in the tens of MB.

The streaming approach uses Laravel's built-in `lazyById()`, which:

- Issues `SELECT … WHERE tv.id > $last ORDER BY tv.id LIMIT 2000` repeatedly — keyset pagination, no `OFFSET` cost.
- Returns plain `stdClass` rows from the query builder — no Eloquent overhead.
- Yields rows one at a time through a `LazyCollection`, so the working set is bounded to one chunk regardless of table size.

The service iterates the lazy collection once, building the locale-keyed map directly. The result is cached for 60 seconds, so subsequent requests skip the DB entirely.

## Write flow

```
POST /api/translations
        │
        ▼
StoreTranslationRequest (validation)
        │
        ▼
TranslationController::store
        │
        ▼
TranslationService::create (DB::transaction)
        │
        ├── TranslationRepository::createKey
        ├── TranslationRepository::upsertValues   (single INSERT … ON CONFLICT)
        ├── TagRepository::resolveByNames         (≤ 3 round-trips, batched insert)
        └── TranslationRepository::syncTags
        │
        ▼
Cache::flush()  ← invalidates export cache
        │
        ▼
JSON response
```

Updates follow the same shape; `upsertValues` lets a single PUT both create new locales and overwrite existing ones in one statement.
