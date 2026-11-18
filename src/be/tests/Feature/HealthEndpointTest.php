<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9: /health/live and /health/ready, registered outside the api/web
 * middleware groups in bootstrap/app.php (same convention as Laravel's
 * built-in /up). Deliberately unauthenticated -- this is what the
 * compose/orchestrator health-gate and the Docker HEALTHCHECK poll.
 */
class HealthEndpointTest extends TestCase
{
    public function test_live_endpoint_reports_ok_without_authentication(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_ready_endpoint_reports_ok_when_the_database_is_reachable(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_ready_endpoint_reports_503_when_the_database_is_unreachable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \PDOException('connection refused'));

        // Laravel's own test teardown (disconnectDatabaseConnections(), run
        // after every test) calls DB::getConnections() on whatever "db"
        // currently resolves to, to disconnect every open connection. Since
        // shouldReceive() above replaced the real DatabaseManager singleton
        // with this Mockery mock for the rest of the test, teardown calls
        // getConnections() on the SAME mock -- which needs its own
        // expectation or Mockery raises a BadMethodCallException. Returning
        // an empty array is accurate here: this mock never registers a
        // real connection to disconnect.
        DB::shouldReceive('getConnections')->andReturn([]);

        $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('checks.database', 'error');
    }
}
