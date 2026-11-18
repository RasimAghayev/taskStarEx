<?php

namespace Tests;

use App\Models\User as AuthUser;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    private Generator $faker;

    public function setUp()
    : void {

        parent::setUp();
        $this->faker = Factory::create();
        Artisan::call('migrate:refresh');
    }

    public function __get($key) {

        if ($key === 'faker')
            return $this->faker;
        throw new Exception('Unknown Key Requested');
    }

    /**
     * Phase 9: /api/company/users/* now requires a valid Sanctum token.
     * Sanctum::actingAs() is Sanctum's own documented testing helper --
     * it authenticates the test's HTTP kernel calls directly against the
     * 'sanctum' guard without needing a real token round-trip. Returns
     * the created user so a test can assert against it (e.g. audit log
     * performed_by).
     */
    protected function actingAsApiUser(): AuthUser
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
