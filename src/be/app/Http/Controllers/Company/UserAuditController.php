<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\UserAuditLogResource;
use App\Models\Company\User as CompanyUser;
use App\Models\Company\UserAuditLog;
use Illuminate\Http\Request;

class UserAuditController extends Controller
{
    /**
     * Read-only change history for one company user, newest first.
     *
     * Deliberately does NOT look up the company_users row first (e.g. via
     * UserRepositoryInterface::getByFin(), which 404s on a missing row) --
     * the whole point of this endpoint is that it keeps working after the
     * parent row has been hard-deleted. Authorized against the same
     * viewAny ability as the company-user list (Phase 9): there is no
     * surviving model instance to check a per-record ability against once
     * the parent row is gone, and this repo has no per-record ownership
     * concept anyway (see UserPolicy's docblock).
     */
    public function index(Request $request, string $fin)
    {
        $this->authorize('viewAny', CompanyUser::class);

        $logs = UserAuditLog::query()
            ->where('fin', $fin)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return UserAuditLogResource::collection($logs)->additional(['success' => true]);
    }
}
