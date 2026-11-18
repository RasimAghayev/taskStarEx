<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\UserAuditLogResource;
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
     * parent row has been hard-deleted.
     */
    public function index(Request $request, string $fin)
    {
        $logs = UserAuditLog::query()
            ->where('fin', $fin)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return UserAuditLogResource::collection($logs)->additional(['success' => true]);
    }
}
