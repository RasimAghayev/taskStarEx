<?php

namespace Tests\Feature\Company;

use App\Models\Company\User;
use App\Models\Company\UserAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyUserAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_writes_exactly_one_created_audit_row(): void
    {
        $user = User::factory()->create(['fin' => 'AU11111', 'name' => 'Ali']);

        $this->assertDatabaseCount('company_user_audit_logs', 1);

        $log = UserAuditLog::where('fin', 'AU11111')->first();
        $this->assertSame('created', $log->action);
        $this->assertSame('Ali', $log->changes['name']);
        $this->assertNull($log->performed_by);
    }

    public function test_updating_only_status_records_only_that_fields_old_and_new_value(): void
    {
        $user = User::factory()->create(['fin' => 'AU22222', 'status' => 'new_register']);

        $user->update(['status' => 'it_works']);

        $this->assertDatabaseCount('company_user_audit_logs', 2); // 1 created + 1 updated

        $log = UserAuditLog::where('fin', 'AU22222')->where('action', 'updated')->first();
        $this->assertSame(['status' => ['old' => 'new_register', 'new' => 'it_works']], $log->changes);
    }

    public function test_deleting_a_user_writes_a_deleted_audit_row_that_survives_the_hard_delete(): void
    {
        $this->actingAsApiUser();
        $user = User::factory()->create(['fin' => 'AU33333', 'email' => 'au3@example.com']);

        $user->delete();

        $this->assertDatabaseMissing('company_users', ['fin' => 'AU33333']);

        $log = UserAuditLog::where('fin', 'AU33333')->where('action', 'deleted')->first();
        $this->assertNotNull($log);
        $this->assertSame('au3@example.com', $log->changes['email']);

        // Still readable via the public endpoint after the parent row is gone.
        // Two rows are expected here: the factory's own create() already
        // wrote a "created" row, and delete() just added the "deleted" one.
        $response = $this->getJson('/api/company/users/AU33333/audit');
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('deleted', $response->json('data.0.action'));
        $this->assertSame('created', $response->json('data.1.action'));
    }

    public function test_audit_endpoint_returns_newest_first_and_paginates(): void
    {
        $this->actingAsApiUser();
        $user = User::factory()->create(['fin' => 'AU44444', 'status' => 'new_register']);
        $user->update(['status' => 'it_works']);
        $user->update(['status' => 'vacation']);

        $response = $this->getJson('/api/company/users/AU44444/audit?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('vacation', $response->json('data.0.changes.status.new'));
        $this->assertSame('it_works', $response->json('data.1.changes.status.new'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_performed_by_is_populated_for_an_authenticated_http_write(): void
    {
        $actor = $this->actingAsApiUser();

        $response = $this->postJson('/api/company/users', [
            'fin' => 'AU66666',
            'name' => 'Rasim',
            'surname' => 'Aghayev',
            'father' => 'Ilham',
            'dob' => '1990-05-10',
            'email' => 'performed-by@example.com',
            'status' => 'new_register',
        ]);

        $response->assertCreated();

        $log = UserAuditLog::where('fin', 'AU66666')->where('action', 'created')->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->email, $log->performed_by);
    }

    public function test_an_audit_write_failure_does_not_block_the_underlying_business_operation(): void
    {
        // Force the observer's UserAuditLog::create() to fail by removing
        // the table it writes to -- SQLite DDL participates in the
        // RefreshDatabase transaction, so this is rolled back after this
        // test and does not affect any other test.
        Schema::drop('company_user_audit_logs');

        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return $context['action'] === 'created' && $context['fin'] === 'AU55555';
        });

        $user = User::create([
            'fin' => 'AU55555',
            'name' => 'Resilient',
            'surname' => 'User',
            'father' => 'Dad',
            'dob' => '1990-01-01',
            'email' => 'au5@example.com',
            'status' => 'new_register',
        ]);

        $this->assertDatabaseHas('company_users', ['fin' => 'AU55555']);
        $this->assertSame('AU55555', $user->fin);
    }
}
