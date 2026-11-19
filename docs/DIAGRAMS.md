# Diagrams

> Phase 15. Every diagram below is derived directly from real code/config
> (cited next to each) — `docker-compose.yml`, `routes/api.php`,
> `bootstrap/app.php`, the controllers/observers/migrations, and this
> repo's own `repo-manifest.yaml`. Nothing is invented; anything not
> directly confirmed in code is marked **unconfirmed**. See
> `docs/ARCHITECTURE.md` for the prose version of most of this.

## 1. System context

Who/what talks to what, at the container level
(`docker-compose.yml`, `.env.example`):

```mermaid
flowchart LR
    user(["Browser / user"])

    subgraph compose["Docker Compose network: teststarex"]
        fe["fe<br/>Nuxt 4 SPA<br/>:FE_PORT (20251)"]
        nginx["nginx<br/>:APP_PORT (20250)"]
        php["php<br/>Laravel 13 API (php-fpm)"]
        pgsql[("pgsql<br/>PostgreSQL 18.7<br/>:POSTGRES_PORT (20252)")]
    end

    user -- "HTTP :20251 (web UI)" --> fe
    user -- "HTTP :20250 /api/* (direct API use)" --> nginx
    fe -- "axios, NUXT_PUBLIC_API_URL<br/>Bearer token (Sanctum)" --> nginx
    nginx -- "FastCGI :9000" --> php
    php -- "Eloquent" --> pgsql
```

`nginx`'s healthcheck dependency on `php` and the dev-only `php-dev`/
`fe-dev` tool-runner services (Compose `tools` profile) are omitted here
for clarity — see `docs/DEPLOYMENT.md` for the full service table.

## 2. Dependency chain (cross-repo ecosystem)

Per `repo-manifest.yaml` and `projects/info/DEPENDENCY_MAP.md` (Phase 2,
confirmed independently): **taskStarEx has zero dependencies on, and
zero dependents from, any other `RasimAghayev` repository.**

```mermaid
graph LR
    taskStarEx["taskStarEx (service)<br/>runs_standalone: true"]

    taskStarEx -.->|"depends_on: services=[] libraries=[]<br/>(confirmed empty)"| isolated(["no other RasimAghayev repo"])
```

This is confirmed isolation, not an unchecked gap — both this repo's own
Phase 2 pass and the program-wide `DEPENDENCY_MAP.md` independently
reached the same conclusion.

For completeness, the real dependency chain that *does* exist is
internal (module-level, within this one repo):

```mermaid
graph LR
    route["routes/api.php"] --> ctrl["UserController /<br/>UserAuditController /<br/>AuthController"]
    ctrl --> policy["UserPolicy<br/>(authorize)"]
    ctrl --> reqv["StoreUserRequest /<br/>UpdateUserRequest /<br/>ImportUsersRequest"]
    ctrl --> repoIface["UserRepositoryInterface"]
    repoIface --> repoImpl["UserRepository"]
    repoImpl --> model["Company\\User<br/>(Eloquent)"]
    model -->|"created/updated/deleted<br/>observer event"| observer["UserAuditObserver"]
    observer --> auditModel["UserAuditLog"]
    model --> db1[("company_users")]
    auditModel --> db2[("company_user_audit_logs")]
```

## 3. Core flows

### 3.1 Login (token issuance)

`routes/api.php`, `AuthController::login`:

```mermaid
sequenceDiagram
    actor U as Browser (Nuxt SPA)
    participant Nx as nginx
    participant A as AuthController

    U->>Nx: POST /api/login {email, password}
    Nx->>A: forward (throttle:login — 5/min by IP)
    A->>A: Auth::validate(credentials)
    alt invalid credentials
        A-->>U: 401 {success:false, message:"Invalid credentials"}
    else valid
        A->>A: user.createToken('api')
        A-->>U: 200 {token, token_type:"Bearer", user}
    end
```

### 3.2 Company-user CRUD (create, with audit write)

`UserController::store`, `UserPolicy`, `UserRepository`,
`UserAuditObserver` — representative of create/update/delete, all of
which trigger the same observer-based audit write:

```mermaid
sequenceDiagram
    actor U as Browser (Bearer token)
    participant C as UserController
    participant P as UserPolicy
    participant R as UserRepository
    participant M as Company\\User (Eloquent)
    participant O as UserAuditObserver
    participant DB as PostgreSQL

    U->>C: POST /api/company/users
    C->>P: authorize('create')
    P-->>C: true (authenticated — single-actor model, ADR-0004)
    C->>R: store(validated)
    R->>M: create(...)
    M->>DB: INSERT company_users
    M->>O: "created" event (Eloquent model observer)
    O->>DB: INSERT company_user_audit_logs (action="created")
    C-->>U: 201 {success, data}
```

The audit write happens automatically via Laravel's model-observer
mechanism — `UserController`/`UserRepository` have no explicit
audit-logging call; `UserAuditObserver` is registered once on
`Company\User` in `AppServiceProvider::boot()`.

### 3.3 CSV export

`UserController::export`, `App\Support\Csv\CsvSafety`:

```mermaid
sequenceDiagram
    actor U as Browser
    participant C as UserController
    participant R as UserRepositoryInterface
    participant CS as CsvSafety

    U->>C: GET /api/company/users/export?search=...
    C->>C: authorize('viewAny')
    C->>R: all(search)
    R-->>C: matching rows
    loop each row, each string cell
        C->>CS: sanitizeForExport(value)
    end
    C-->>U: streamed CSV (Content-Type: text/csv)
```

### 3.4 CSV bulk import

`UserController::import`, `ImportUsersRequest`, `CsvSafety`:

```mermaid
sequenceDiagram
    actor U as Browser
    participant C as UserController
    participant CS as CsvSafety
    participant V as Validator (StoreUserRequest rules)
    participant R as UserRepositoryInterface

    U->>C: POST /api/company/users/import (multipart CSV)
    Note over C: throttle:import — 10/min
    C->>C: authorize('create')
    C->>C: validate header row matches expected columns
    loop each data row
        C->>CS: isFormulaInjection(any field)?
        alt formula-injection char ("=","+","-","@")
            C->>C: record row error (reject, do not rewrite)
        else duplicate fin/email already seen in this file
            C->>C: record row error
        else
            C->>V: Validator::make(row, rules)
            alt validation fails
                C->>C: record row error
            else passes
                C->>R: store(validated row)
            end
        end
    end
    C-->>U: 200 {created, failed, errors:[{row, fin, messages}]}
```

### 3.5 Audit-log read

`UserAuditController::index` — deliberately does not look up the parent
`company_users` row first, so history survives a hard delete:

```mermaid
sequenceDiagram
    actor U as Browser
    participant AC as UserAuditController

    U->>AC: GET /api/company/users/{fin}/audit
    AC->>AC: authorize('viewAny', CompanyUser::class)
    AC->>AC: UserAuditLog::where(fin).orderByDesc(created_at, id).paginate()
    AC-->>U: 200 {success, data:[...], links, meta}
```

## 4. Data model

From `database/migrations/*` (`company_users`, `company_user_audit_logs`,
`personal_access_tokens`, Laravel's default `users` migration):

```mermaid
erDiagram
    company_users {
        varchar fin PK
        varchar name
        varchar surname
        varchar father
        date dob
        varchar email
        enum status
        timestamp created_at
        timestamp updated_at
    }
    company_user_audit_logs {
        bigint id PK
        varchar fin
        enum action
        json changes
        varchar performed_by
        timestamp created_at
    }
    users {
        bigint id PK
        varchar name
        varchar email
        varchar password
        timestamp created_at
        timestamp updated_at
    }
    personal_access_tokens {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        varchar name
        varchar token
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    company_users ||..o{ company_user_audit_logs : "fin (no FK — deliberate; a row must survive the parent's hard delete)"
    users ||--o{ personal_access_tokens : "tokenable (Sanctum)"
```

Notes, all confirmed in code (none invented):

- `company_users.fin` is the primary key (not an auto-increment id) —
  a 7-character Azerbaijan-national-ID-style string.
- `company_user_audit_logs` has **no foreign key** to `company_users` by
  design (see the migration's own doc-comment) — append-only, must
  outlive the row it describes.
- `users`/`personal_access_tokens` are Laravel's/Sanctum's own tables.
  `users` was originally unused scaffolding (Phase 3 finding) but Phase
  9 wired real token authentication against it — it is genuinely live
  today, not dead code.
- `password_reset_tokens`, `sessions`, and Laravel's default
  cache/jobs tables also exist (from the same default migration) and
  remain unused — omitted above since nothing in this app's real flows
  touches them. There is **no `departments` table** — none exists in
  `database/migrations/`; confirmed by listing the directory, not
  assumed.

## 5. Deployment topology

Same services as §1, with build targets and persistent volumes
(`docker-compose.yml`, `*.dockerfile`):

```mermaid
flowchart TB
    subgraph host["Host machine"]
        subgraph net["Docker network: teststarex"]
            nginx["nginx<br/>(nginx.dockerfile)<br/>published :20250→80"]
            php["php<br/>(php.dockerfile, target: runtime)"]
            fe["fe<br/>(node.dockerfile, target: runtime)<br/>published :20251→3000"]
            pgsql[("pgsql<br/>postgres:18.7 (pinned by digest)<br/>published :20252→5432")]
        end
        vol1[("volume: pgsql_data")]
        vol2[("volume: be_storage")]
    end

    nginx -->|depends_on: service_healthy| php
    php -->|depends_on: service_healthy| pgsql
    php --- vol2
    pgsql --- vol1
```

Not shown (dev-only, not part of `docker compose up`): `php-dev`/`fe-dev`
tool-runner services under the Compose `tools` profile
(`docs/DEPLOYMENT.md`). There is no staging/production topology — this
*is* the only environment this repo has today.

## 6. Event flow

**N/A.** This repo has no event-driven architecture (standing decision 7
/ Phase 8's own EDA assessment: "no async reaction or cross-module/
cross-service integration exists or is planned"). The one
observer-pattern mechanism in this codebase (`UserAuditObserver`) is a
synchronous, in-process Eloquent model-event listener, not an async
event/message system — already shown as part of the core-flow diagram
in §3.2, not duplicated as a separate "event flow" diagram.
