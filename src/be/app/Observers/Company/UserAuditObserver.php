<?php

namespace App\Observers\Company;

use App\Models\Company\User;
use App\Models\Company\UserAuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Writes one company_user_audit_logs row for every created/updated/deleted
 * company_users row. Registered on App\Models\Company\User in
 * AppServiceProvider::boot() -- a plain Eloquent model observer, Laravel's
 * own built-in mechanism, so UserController/UserRepository stay completely
 * unchanged (zero risk to the already-tested CRUD behavior).
 *
 * An audit-write failure must never block or roll back the business
 * operation it is observing -- each handler is wrapped in its own
 * try/catch and only logs on failure.
 */
class UserAuditObserver
{
    public function created(User $user): void
    {
        $this->record($user, 'created', $this->scalarize($user->only($user->getFillable())));
    }

    public function updated(User $user): void
    {
        $changes = [];

        foreach ($user->getChanges() as $field => $newValue) {
            if ($field === 'updated_at') {
                continue;
            }

            $changes[$field] = [
                'old' => $this->scalarValue($user->getOriginal($field)),
                'new' => $this->scalarValue($newValue),
            ];
        }

        if ($changes === []) {
            return;
        }

        $this->record($user, 'updated', $changes);
    }

    public function deleted(User $user): void
    {
        $this->record($user, 'deleted', $this->scalarize($user->only($user->getFillable())));
    }

    /**
     * company_users::status is cast to the UserStatus backed enum. Storing
     * it directly in the JSON `changes` column would rely on PHP's enum ->
     * json_encode() behavior; normalizing to the plain scalar backing value
     * up front keeps the stored payload unambiguous and dependency-free.
     */
    private function scalarize(array $attributes): array
    {
        return array_map(fn ($value) => $this->scalarValue($value), $attributes);
    }

    private function scalarValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    private function record(User $user, string $action, array $changes): void
    {
        try {
            UserAuditLog::create([
                'fin' => $user->getKey(),
                'action' => $action,
                'changes' => $changes,
                'performed_by' => null, // no authentication exists yet (Phase 9); Phase 9 backfills this going forward once it does
            ]);
        } catch (\Throwable $ex) {
            Log::error('Failed to write company user audit log', [
                'fin' => $user->getKey(),
                'action' => $action,
                'exception' => $ex,
            ]);
        }
    }
}
