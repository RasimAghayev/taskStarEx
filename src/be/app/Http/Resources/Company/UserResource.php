<?php

namespace App\Http\Resources\Company;

use App\Models\Company\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase 9 (quality tooling): a JsonResource proxies undeclared property
 * access to its wrapped model via __get() at runtime, which PHPStan/
 * Larastan cannot see without this -- the documented fix for Larastan's
 * "Access to an undefined property *Resource::$x" finding on a
 * JsonResource, not a behavior change.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dob = $this->dob;
        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'fin' => $this->fin,
            'name' => $this->name,
            'surname' => $this->surname,
            'father' => $this->father,
            'dob' => $dob ? $dob->format('Y-m-d') : null,
            'dob_display' => $dob ? $dob->format('d.m.Y') : null,
            'email' => $this->email,
            'status' => $this->status?->value,
            'status_label' => $this->status?->description(),
            'created_at' => $createdAt?->format('d.m.Y H:i:s'),
            'updated_at' => $updatedAt?->format('d.m.Y H:i:s'),
        ];
    }
}
