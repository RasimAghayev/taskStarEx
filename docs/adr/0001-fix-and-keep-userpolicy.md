# ADR-0001: Fix and keep the `UserPolicy` scaffold

- **Status**: Accepted
- **Date**: 2026-11-18
- **Phase**: 8 (Architecture)

## Context

`src/be/app/Policies/Company/UserPolicy.php` is a Laravel policy scaffold
with all 7 standard `make:policy --model` methods (`viewAny`, `view`,
`create`, `update`, `delete`, `restore`, `forceDelete`) stubbed to an empty
body (`//`) despite a `: bool` return type on every method. It is never
invoked anywhere in the codebase today — confirmed by Phases 3, 7, and
re-confirmed independently in this phase via `grep -rn "UserPolicy\|authorize(\|Gate::" app/ routes/ bootstrap/`
(the only other `authorize()` hit is `ApiFormRequest::authorize()`, an
unrelated Laravel `FormRequest` hook that always returns `true` — not a
`Gate`/Policy call). No `AuthServiceProvider` or other provider registers
it explicitly either (`app/Providers/` contains only `AppServiceProvider`
and `RepositoryServiceProvider`; `bootstrap/providers.php` lists only
those two).

If any code ever did call `$this->authorize(...)`/`Gate::allows(...)`
against it today, PHP would throw `TypeError: Return value must be of
type bool, none returned` — the class is not just unused, it is broken if
used.

One relevant fact discovered during this phase's investigation: the class
already sits at the exact namespace/class-name Laravel's policy
auto-discovery convention expects for its model
(`App\Models\Company\User` -> `App\Policies\Company\UserPolicy`). No
explicit registration is or would be needed for Phase 9 to start calling
`$this->authorize('view', $companyUser)` in `UserController` — Laravel's
`Gate::getPolicyFor()` will resolve it by convention alone.

`/api/company/users/*` (the repo's real CRUD routes) currently has zero
authentication or authorization middleware (`src/be/routes/api.php:11`,
confirmed by Phases 2/3/7). Full PII (name, surname, father's name, DOB,
email) is readable/writable/deletable by anyone who can reach the API.
Wiring real enforcement is explicitly Phase 9's job, alongside the
separate, also-explicitly-deferred question of whether to install Sanctum
or another auth stack — this ADR does not decide or touch either.

## Decision

**Fix the class's internal correctness; keep it as the Policy Phase 9
wires in.** Do not delete it.

Every method now has an explicit `return false;` (fail-closed /
deny-by-default) instead of no return statement. Rationale for `false`
specifically, not `true`:

- Returning `true` everywhere would make this policy a no-op the moment
  it's wired — behaviorally identical to having no policy at all (today's
  actual status quo), which provides zero authorization value and would
  misleadingly look like "access control exists" in a code review when it
  doesn't.
- Returning `false` everywhere is a safe, honest placeholder: it fails
  closed, costs nothing today (the class still isn't invoked by anything),
  and forces Phase 9 to make an explicit, visible decision for each action
  once it designs real authentication/roles — rather than silently
  inheriting a blanket allow nobody chose.
- This repo has no role or ownership model today (single implicit actor
  type, no `roles` table, no per-record ownership concept) — there is no
  real business rule available to encode yet. Inventing one here (e.g. "only
  admins can delete") would be fabricating business logic I have no
  authority to decide; that is explicitly reserved for Phase 9's real
  authentication/authorization design, per the orchestrator's directive for
  this phase.
- The unused `use Illuminate\Auth\Access\Response;` import was also removed
  as part of making this file internally correct — no method uses it.

## Alternatives considered

1. **Delete the class, let Phase 9 write policies from scratch.** Rejected:
   the class already sits at the correct auto-discovery location/name,
   already lists the correct 7 standard CRUD-adjacent methods for this
   model, and its only defect is the missing return statements — deleting
   working scaffolding to recreate the identical shape later is pure
   busywork, not a simplification. Standing decision 6 ("dead is proven by
   tooling + tests, not guessed") also doesn't apply here in the usual
   sense: this class isn't dead *code* in the sense of "safe to delete
   because nothing needs it" — it's scaffolding Phase 9 is explicitly
   planned to need (`docs/IMPROVEMENT_ROADMAP.md` Phase 9: "per Phase 8's
   ADR on `UserPolicy`").
2. **Return `true` everywhere (fail-open).** Rejected — see Decision above;
   provides no real authorization value and risks looking like genuine
   access control when it is not.
3. **Wire it into the controller/routes now.** Rejected — out of this
   phase's scope per the orchestrator's explicit directive: "real
   auth/authz enforcement is explicitly scoped there [Phase 9], alongside
   the inert default-Laravel-auth-stack/Sanctum question." Phase 9 owns
   both the authentication mechanism and the authorization wiring as one
   coherent piece of work.

## Consequences

- `UserPolicy` is now internally correct PHP (type-safe, no runtime
  `TypeError` if ever invoked) but has **zero behavioral effect today** —
  it is still not called from anywhere. This is intentional: Phase 8's
  scope is the class's own correctness and continued existence, not
  enforcement.
- Phase 9 inherits a ready-to-wire, convention-correct Policy class and
  only needs to (a) decide real per-action rules once authentication
  exists, and (b) add `$this->authorize(...)` calls (or route
  middleware) in `UserController`/`routes/api.php` — no policy
  registration boilerplate needed, Laravel's convention already resolves
  it.
- If Phase 9 instead decides a different authorization shape is better
  (e.g., a single `Gate::define` closure, or Form Request-level
  authorization instead of a Policy class), it is free to replace this
  file — this ADR documents Phase 8's decision, not a permanent
  constraint on Phase 9's own design authority.

## Verification

- `grep -rn "UserPolicy\|authorize(\|Gate::" app/ routes/ bootstrap/` (src/be,
  this phase) — confirms it is still never invoked anywhere, so this change
  has no runtime effect to verify beyond "the file is valid PHP."
- `docker build --target test -f php.dockerfile .` (fresh build) — exit 0,
  **8 tests passed, 31 assertions**, unchanged from `docs/BASELINE.md`'s
  contract (no existing test exercises this policy, consistent with it
  never being wired in).
