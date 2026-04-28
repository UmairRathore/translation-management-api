# Translation Management API

A read-heavy translation management service built with Laravel 13, PostgreSQL, and a database cache layer.

Manages translation keys across multiple locales, supports tagging and search, and exposes a fast JSON export endpoint suitable for client/CDN consumption.

> Demo: _Loom link placeholder — to be added._

---

## Stack

- **PHP** 8.3+
- **Laravel** 13
- **PostgreSQL** (primary database)
- **Database cache driver** (Redis-ready — see [Caching](#caching))
- **Sanctum** for token authentication
- **L5-Swagger** for OpenAPI docs

> **Swagger UI available at `/api/documentation` once the server is running.**

---

## Setup

```bash
git clone <repo>
cd translation-management-api

composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=translation_management
DB_USERNAME=postgres
DB_PASSWORD=

CACHE_STORE=database
```

Create the database, then:

```bash
php artisan migrate
php artisan l5-swagger:generate
php artisan serve
```

Server runs at `http://localhost:8000`.

---

## Seeding test data (100k+ records)

To populate the database with 100,000 translation keys (each with `en` + `fr` values and 1–3 random tags), run:

```bash
php artisan db:seed --class=TranslationSeeder
```

The seeder uses chunked bulk inserts (1,000 keys per batch) and produces:

| Table | Rows |
|---|---|
| `tags` | 10 |
| `translation_keys` | 100,000 |
| `translation_values` | 200,000 |
| `translation_tag` | ~200,000 |

Use this dataset to validate the `<500ms` export budget end-to-end on PostgreSQL.

---

## Running tests

```bash
php artisan test
```

Tests use SQLite in-memory and the `array` cache driver — nothing else needs to be running.

---

## API summary

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/api/auth/token` | public | Issue a Sanctum token |
| `GET` | `/api/translations` | public | Search keys (`key`, `content`, `locale`, `tags[]`, `per_page`) |
| `GET` | `/api/translations/export` | public | Locale-keyed JSON export, cached |
| `GET` | `/api/translations/{key}` | public | Fetch a single key |
| `POST` | `/api/translations` | sanctum | Create a key with locale values + tags |
| `PUT` | `/api/translations/{key}` | sanctum | Update locale values and (optionally) tags |

### Export response shape

```json
{
  "en": { "welcome.title": "Welcome", "auth.login": "Sign in" },
  "fr": { "welcome.title": "Bienvenue", "auth.login": "Connexion" }
}
```

### Quick start

Fetch the full export (no auth required):

```bash
curl http://localhost:8000/api/translations/export
```

Issue a token, then use it on write endpoints:

```bash
curl -X POST http://localhost:8000/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret"}'
```

Use the returned token as `Authorization: Bearer <token>` on POST/PUT.

### Swagger UI

Available at `http://localhost:8000/api/documentation`.
Regenerate the spec after editing controller attributes:

```bash
php artisan l5-swagger:generate
```

---

## Caching

The export endpoint is cached at the controller layer with `Cache::remember(...)`, TTL 60 seconds.

- **Cache key:** `translations.export.all` (no locale) or `translations.export.{locale}`.
- **Backing store:** the `database` driver (Laravel `cache` table). Production-grade — works without extra infrastructure.
- **Invalidation:** `Cache::flush()` is called after `POST` and `PUT` writes. Coarse but correct for this scope.

**Switching to Redis** is a `.env` change only — no code change needed:

```
CACHE_STORE=redis
```

---

## Performance notes

- Export streams rows via `lazyById(2000)` over a single indexed JOIN — no N+1, no full-table model hydration.
- Composite unique index `(translation_key_id, locale)` covers the join, point lookups, and the integrity guarantee.
- Paginated search uses `whereHas` correlated subqueries over the indexed pivot for tag filtering.
- A feature test asserts the cold-path export responds within 1000ms over 1k rows on SQLite; the warm path (cache hit) is essentially constant time.

---

## CDN considerations (theoretical)

The export endpoint returns a self-contained JSON document with predictable cache keys per locale. In a production deployment it would sit behind a CDN with:

- `Cache-Control: public, max-age=60, s-maxage=60` matching the application TTL.
- A surrogate key per locale (`translations:en`, `translations:fr`) so writes can purge edge caches selectively.
- Origin shielding so the application cache is hit at most once per region per minute.

The application's existing 60-second TTL plus `Cache::flush()` on writes is consistent with this model — replace `flush()` with a tag-based purge if you adopt cache tagging.
