<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 12-factor/K8s-style liveness and readiness probes (Phase 9). Registered
 * outside the web/api middleware groups (see bootstrap/app.php) -- the same
 * convention Laravel itself uses for the built-in `/up` check -- so a probe
 * never pays for CSRF/session overhead and is never subject to the API's
 * CORS/rate-limit policy.
 */
class HealthController extends Controller
{
    /**
     * Liveness: is the PHP-FPM worker actually executing application code
     * right now? Deliberately checks no external dependency -- that is
     * ready()'s job. An orchestrator should restart the container only
     * when THIS fails, not when a downstream dependency is merely slow.
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Readiness: can this instance actually serve real traffic right now?
     * Checks the one real external dependency this app has -- the
     * database. Failure is logged with the underlying exception (server
     * side only; the response body never leaks exception detail).
     */
    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json([
                'status' => 'ok',
                'checks' => ['database' => 'ok'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Readiness check failed: database unreachable', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'checks' => ['database' => 'error'],
            ], 503);
        }
    }
}
