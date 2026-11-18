<?php

namespace App\Policies\Company;

use App\Models\Company\User as CompanyUser;
use App\Models\User;

/**
 * Authorization rules for the `company_users` business entity.
 *
 * Default-deny placeholder (Phase 8, ADR-0001): this repo has no
 * authentication or role/ownership model today, so there is no real
 * business rule yet to encode here. Every method fails closed (denies)
 * rather than silently allowing everything, which would provide no
 * actual authorization value over having no policy at all.
 *
 * This class is NOT currently wired into any route or controller — it is
 * discoverable by Laravel's policy-auto-discovery convention
 * (`App\Models\Company\User` -> `App\Policies\Company\UserPolicy`) but
 * nothing calls `authorize()`/`Gate::` against it yet. Wiring it into the
 * CRUD routes/controllers, and replacing these deny-by-default bodies with
 * real rules once real authentication exists, is Phase 9's job.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CompanyUser $companyUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CompanyUser $companyUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CompanyUser $companyUser): bool
    {
        return false;
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
