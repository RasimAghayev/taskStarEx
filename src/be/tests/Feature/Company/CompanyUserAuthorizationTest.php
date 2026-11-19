<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 9 P0 fix verification: every /api/company/users/* route (including
 * the Phase 8B export/import/audit additions) must reject an unauthenticated
 * caller with 401 -- before this phase these routes had no auth middleware
 * at all and returned the real data to anyone.
 */
class CompanyUserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/company/users')->assertStatus(401);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/company/users', [])->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/company/users/AB12345')->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/company/users/AB12345', [])->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/company/users/AB12345')->assertStatus(401);
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/company/users/export')->assertStatus(401);
    }

    public function test_import_requires_authentication(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csvimport');
        file_put_contents($path, "fin,name,surname,father,dob,email,status\n");
        $file = new UploadedFile($path, 'import.csv', 'text/csv', null, true);

        $this->postJson('/api/company/users/import', ['file' => $file])->assertStatus(401);
    }

    public function test_audit_log_requires_authentication(): void
    {
        $this->getJson('/api/company/users/AB12345/audit')->assertStatus(401);
    }

    /**
     * Regression test for a real defect found during this phase's manual
     * Docker smoke test (not via getJson(), which always sends
     * "Accept: application/json" and therefore never exercised this path):
     * a plain request with no Accept header made Laravel's default
     * Authenticate middleware try to redirect to route('login'), which
     * doesn't exist in this pure-JSON-API app (no web session guard, see
     * docs/adr/0004-...), throwing RouteNotFoundException -> a bare 500
     * instead of a clean 401. bootstrap/app.php's withExceptions() now
     * renders every AuthenticationException as JSON regardless of the
     * caller's Accept header.
     */
    public function test_index_requires_authentication_even_without_an_accept_json_header(): void
    {
        $this->get('/api/company/users')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
