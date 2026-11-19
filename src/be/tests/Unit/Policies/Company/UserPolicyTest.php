<?php

namespace Tests\Unit\Policies\Company;

use App\Models\Company\User as CompanyUser;
use App\Models\User;
use App\Policies\Company\UserPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Phase 11: locks in the real, documented authorization model so a future
 * change to `UserPolicy` is a deliberate, visible decision rather than an
 * accidental regression -- in either direction.
 *
 * This repo's `UserPolicy` went through two distinct, deliberate shapes:
 *   - Phase 8 (ADR-0001): every method fail-closed (`return false;`), a
 *     safe placeholder because no authentication existed yet.
 *   - Phase 9 (ADR-0004): wired for real. Five methods (`viewAny`, `view`,
 *     `create`, `update`, `delete`) now return `true` -- this app has
 *     exactly one class of authenticated actor and no role/ownership
 *     model (ADR-0004 explicitly considered and rejected inventing one,
 *     YAGNI/KISS). The actual security boundary is the `auth:sanctum`
 *     middleware; this policy is the second, explicit enforcement layer.
 *     `restore`/`forceDelete` stay fail-closed since `company_users` rows
 *     are hard-deleted (no `SoftDeletes`) and neither action has a route.
 *
 * Without a test naming this directly, a future reader could "fix" the
 * five `true` methods back to `false` (reasonably assuming `false` is
 * always the safer default) without realizing that would make every
 * `/api/company/users/*` write/read action permanently 403 for every
 * authenticated user -- ADR-0004 explicitly rejected that exact outcome
 * (see its "Alternatives considered" #4). This test is the guard rail.
 */
class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    private User $actor;

    private CompanyUser $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy;
        // Unsaved, in-memory instances are enough: not one of these five
        // methods inspects the actor or target at all (see ADR-0004) --
        // the single-actor model's whole point is that it doesn't need to.
        $this->actor = new User;
        $this->target = new CompanyUser;
    }

    public function test_any_authenticated_user_may_view_any_company_user(): void
    {
        $this->assertTrue($this->policy->viewAny($this->actor));
    }

    public function test_any_authenticated_user_may_view_a_specific_company_user(): void
    {
        $this->assertTrue($this->policy->view($this->actor, $this->target));
    }

    public function test_any_authenticated_user_may_create_a_company_user(): void
    {
        $this->assertTrue($this->policy->create($this->actor));
    }

    public function test_any_authenticated_user_may_update_a_company_user(): void
    {
        $this->assertTrue($this->policy->update($this->actor, $this->target));
    }

    public function test_any_authenticated_user_may_delete_a_company_user(): void
    {
        $this->assertTrue($this->policy->delete($this->actor, $this->target));
    }

    /**
     * Fail-closed, by design, because the action is unreachable: no route
     * calls `restore()` (no `SoftDeletes` on `company_users`) -- see
     * ADR-0004's closing note. Returning `true` for something nothing can
     * trigger would not make the app more permissive, only misleading.
     */
    public function test_restore_is_fail_closed_for_every_user_regardless_of_authentication(): void
    {
        $this->assertFalse($this->policy->restore($this->actor, $this->target));
    }

    /**
     * Same reasoning as restore() above -- unreachable, kept fail-closed.
     */
    public function test_force_delete_is_fail_closed_for_every_user_regardless_of_authentication(): void
    {
        $this->assertFalse($this->policy->forceDelete($this->actor, $this->target));
    }
}
