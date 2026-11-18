# Architecture

> Written during Phase 3 (audit/plan). Describes the system **as it exists today** on `master` (`6e9bb01`) — no code changes were made to produce this document.

## Overview

`taskStarEx` is a two-tier demo application built as an interview take-home assignment: a Laravel REST API backend (`src/be`) and a Nuxt/Vue 3 single-page frontend (`src/fe`), talking to each other only over HTTP. There is no shared code between the two app roots, no monorepo build tooling, and no other services.

```
┌─────────────────────┐        HTTP (axios)        ┌──────────────────────────┐
│   src/fe  (Nuxt 3)   │ ───────────────────────▶   │  nginx :80               │
│   Vuetify + Vue 3    │   NUXT_PUBLIC_API_URL       │  → php-fpm :9000         │
│   :3000 / :5173      │   = http://localhost/api    │  (Laravel, src/be)       │
└─────────────────────┘                             └────────────┬─────────────┘
                                                                  │ Eloquent
                                                                  ▼
                                                        ┌──────────────────┐
                                                        │ PostgreSQL :6432  │
                                                        │ (docker-compose)  │
                                                        └──────────────────┘
```

Docker Compose (`docker-compose.yml`) wires 10 services: `nginx`, `php` (php-fpm), `composer`, `artisan`, `phpunit` (all `php.dockerfile`), `pgsql`, and `fe`/`npm`/`npx` (`node.dockerfile` / `node:current-alpine`) for the frontend. An `apache.dockerfile` + `server/apache/httpd-vhosts.conf` exist on disk but are referenced nowhere (confirmed via grep across `docker-compose.yml`, `Command.txt`, `README.md`) — dead configuration, left in place by this phase (Phase 7's job to remove).

## Backend (`src/be`) — module diagram

```
routes/api.php
  └─ Route::apiResource('/company/users', UserController)   [NO auth/authz middleware]
       │
       ▼
app/Http/Controllers/Company/UserController
  ├─ constructor-injects UserRepositoryInterface
  ├─ index()   → UserRepositoryInterface::index($search, $perPage)
  ├─ store()   → StoreUserRequest (validation) → UserRepositoryInterface::store()
  ├─ show()    → UserRepositoryInterface::getByFin()
  ├─ update()  → UpdateUserRequest (validation) → UserRepositoryInterface::update()
  └─ destroy() → UserRepositoryInterface::delete()
       │
       ▼
app/Interfaces/Company/UserRepositoryInterface  ← bound in app/Providers/RepositoryServiceProvider
       │
       ▼
app/Repository/Company/UserRepository
  └─ Eloquent queries against App\Models\Company\User (table: company_users)

Response shaping:
  app/Http/Resources/Company/UserResource   — per-record JSON shape (adds dob_display, status_label)
  app/Classes/ApiResponseClass               — success/message/data envelope + transaction rollback helper
  app/Http/Requests/ApiFormRequest           — forces JSON 422 on validation failure (not Laravel's default redirect)

Authorization scaffold (present, NOT wired to anything):
  app/Policies/Company/UserPolicy            — all 6 methods stubbed, no return statement, never invoked
```

A second, parallel, **entirely unused** domain exists in the same codebase: Laravel's default auth scaffolding.

```
database/migrations/0001_01_01_000000_create_users_table.php   → users, password_reset_tokens, sessions
app/Models/User.php                                              → default Authenticatable model
database/factories/UserFactory.php                               → factory for the above
database/seeders/DatabaseSeeder.php                               → seeds one row into `users`
routes/api.php:7-9                                                → GET /user, middleware('auth:sanctum')
```

None of this is reachable in a working state: Sanctum is **not installed** (confirmed by a structural parse of `composer.lock` — the one textual hit for `"laravel/sanctum"` is inside `nunomaduro/collision`'s own unrelated `require-dev` block, not a top-level locked package; confirmed also that `laravel/sanctum` never appears anywhere in `composer.json`'s git history), and `config/auth.php` defines no `sanctum` guard. The `/user` route would throw `InvalidArgumentException: Auth guard [sanctum] is not defined.` if ever hit. It is inert scaffolding inherited from `laravel new`, not a live risk — flagged for a Phase 7/8 keep-or-delete decision.

## Frontend (`src/fe`) — module diagram

```
app.vue                        — v-app shell, navigation drawer, <router-view/> outlet
  └─ layouts/default.vue        — passthrough <slot/>
       └─ pages/ (file-based routing)
            ├─ index.vue
            └─ company/users/
                 ├─ index.vue    — list + search + server-side pagination (v-data-table-server)
                 ├─ create.vue   — create form
                 └─ [fin].vue    — edit form, keyed by route param `fin`

services/companyUsersService.js  — thin wrapper: getCompanyUsers/getCompanyUser/createCompanyUser/updateCompanyUser/deleteCompanyUser
plugins/axiosApiClient.ts         — Nuxt plugin providing $axiosApiClient (baseURL = runtimeConfig.public.apiUrl)
plugins/vuetify.ts                 — Vuetify + @mdi/font registration
components/DatePicker.vue          — present, but not imported/used anywhere (confirmed by grep)
```

`vuex` is a declared dependency (per the original task spec, which mandated Vuex for state management) but is **completely unused**: no `store/` directory, zero `useStore`/`createStore`/`vuex` references anywhere in `src/fe`. All state is local component `data()` — a deliberate (if undocumented) departure from the original spec that works fine at this app's size.

`app.vue` renders `<router-view/>` rather than Nuxt's idiomatic `<NuxtPage/>`. This was **not executed/verified at runtime** in this phase (Phase 3 is documentation-only); flagged for Phase 5/11 to confirm empirically once a working container exists.

## API contract (as implemented)

| Method | Path | Request body | Success response | Notes |
|---|---|---|---|---|
| GET | `/api/company/users?search=&per_page=&page=` | — | `{success, data:[...], links, meta}` (paginated) | search is a parameterized `LIKE`/`ILIKE` (driver-aware) across fin/name/surname/father/email |
| POST | `/api/company/users` | `{fin, name, surname, father, dob, email, status}` | `201 {success, message, data}` | 422 on validation failure |
| GET | `/api/company/users/{fin}` | — | `{success, data}` | 404 behavior on unknown `fin` not confirmed (see Risks) |
| PUT | `/api/company/users/{fin}` | same fields | `{success, message, data}` | |
| DELETE | `/api/company/users/{fin}` | — | `204 No Content` | |

No endpoint requires authentication. No rate limiting beyond whatever Laravel's default `api` middleware group applies (not independently confirmed/tuned here).

## Data model

Single business table, no relations:

```sql
company_users
  fin         varchar  PRIMARY KEY, unique   -- 7-char uppercase alphanumeric (Azerbaijan national ID format)
  name        varchar
  surname     varchar
  father      varchar
  dob         date
  email       varchar  unique
  status      enum(new_register, it_works, vacation, maternity_leave, education, business_trip, out, removed)
  created_at / updated_at
```

Plus the unused default-auth tables (`users`, `password_reset_tokens`, `sessions`, Laravel's cache/jobs tables) — see Backend section above.

## Configuration / environment

- `src/be/.env.example` — placeholders only, compliant with the secrets policy. `DB_CONNECTION=sqlite` by default in the example (the real dev stack uses the `pgsql` Compose service instead — a documentation/example mismatch, not a security issue).
- `src/fe` has **no `.env.example`** despite `nuxt.config.ts` reading `process.env.NUXT_PUBLIC_API_URL` at runtime (one public, non-secret URL) — a documentation gap, not a secrets violation.
- `docker-compose.yml:79-81` hardcodes the dev Postgres credentials directly in the compose file rather than via `.env` — placeholder-style value, local-only port, not a live leak, but inconsistent with how the rest of the stack sources config.
- No `config/cors.php` is published in `src/be/config/` — CORS behavior for the cross-origin dev topology (frontend on :3000/:5173, API behind nginx on :80) depends on undocumented framework defaults. Not verified at runtime in this phase.

## Testing (as implemented)

- Backend: `tests/Feature/Company/CompanyUserApiTest.php` (7 tests: list, create, validation-failure, show, update, search+pagination, delete) using `RefreshDatabase` + factories correctly; `tests/Unit/UserStatusTest.php` (1 test on the enum). This is genuine, working coverage of the CRUD happy paths and validation — not placeholder tests.
- Frontend: no test files exist at all.
- `taskStarEx.postman_collection.json`: requests correctly target the real endpoints with realistic dynamic test data, but every embedded `pm.test()` script asserts a schema (`id`, `username`, `fullName`, `avatar`, `isPublic`) that has nothing to do with this API — generic boilerplate, not adapted. See `docs/REPOSITORY_AUDIT.md` for detail.

## Security posture (as implemented)

- No authentication or authorization on the business CRUD endpoints (full PII exposed without protection).
- A `UserPolicy` exists but is dead scaffolding (never invoked; would error if it were).
- Input validation is real and reasonably thorough (regex-constrained names, FIN format, email uniqueness, enum-constrained status) via `StoreUserRequest`/`UpdateUserRequest`.
- SQL injection: not a concern — the one dynamic query (`UserRepository::index()`'s search) uses Eloquent's parameterized query builder throughout.
- Secrets: redacted working-tree scan (`gitleaks dir . --redact`, this phase) found none. One hardcoded dev-only Postgres placeholder credential noted above (location only).
- Dependency vulnerabilities: see `docs/TECHNICAL_DEBT.md` and `projects/info/deps/taskStarEx.md` for the full, already-quantified list (1 critical CVE on the backend, 70 vulnerable npm packages / 5 critical on the frontend).
