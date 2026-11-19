<?php

namespace App\Http\Resources\Company;

use App\Models\Company\UserAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase 9 (quality tooling): see UserResource's identical annotation for
 * why this is needed -- the documented Larastan fix, not a behavior
 * change.
 *
 * @mixin UserAuditLog
 */
class UserAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fin' => $this->fin,
            'action' => $this->action,
            'changes' => $this->changes,
            'performed_by' => $this->performed_by,
            'created_at' => $this->created_at?->format('d.m.Y H:i:s'),
        ];
    }
}
