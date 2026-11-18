<?php

namespace App\Policies\Company;

use App\Models\Company\User as CompanyUser;
use App\Models\User;

/**
 * Authorization rules for the `company_users` business entity.
 *
 * Phase 8 (ADR-0001) fixed this class's missing-return bug and kept every
 * method fail-closed (`return false;`) as a safe placeholder, since no
 * authentication existed yet to make a real decision against.
 *
 * Phase 9 decision (see docs/adr/0004-wire-authentication-and-authorization.md):
 * this app has exactly one class of authenticated actor -- no roles, no
 * per-record ownership, no multi-tenant concept (confirmed by Phase 3's
 * audit, reaffirmed by Phase 8's ADR-0001) -- and this repo's actual scope
 * does not warrant inventing one (KISS/YAGNI). The real authorization rule
 * is therefore "any authenticated user may manage any company user
 * record"; the actual security boundary is the `auth:sanctum` middleware
 * now wired onto every `/api/company/users/*` route (zero-trust default:
 * no valid token, no access at all) -- this policy is the second,
 * explicit enforcement layer `UserController`/`UserAuditController` call
 * via `$this->authorize(...)`, not a no-op.
 *
 * `restore()`/`forceDelete()` stay fail-closed (`false`): `company_users`
 * rows are hard-deleted (no `SoftDeletes` trait -- confirmed by Phase 8B's
 * audit-log work), so neither action has a route or caller. Returning
 * `true` for something unreachable would not make the app more permissive,
 * only misleading.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CompanyUser $companyUser): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CompanyUser $companyUser): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CompanyUser $companyUser): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CompanyUser $companyUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CompanyUser $companyUser): bool
    {
        return false;
    }
}
