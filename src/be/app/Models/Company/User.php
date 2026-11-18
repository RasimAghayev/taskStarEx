<?php

namespace App\Models\Company;

use App\Enums\UserStatus;
use Database\Factories\Company\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase 9 (quality tooling): declared so PHPStan/Larastan can type-check
 * every real column, and so App\Http\Resources\Company\UserResource's
 * `@mixin User` annotation resolves cleanly -- not a behavior change.
 *
 * @property string $fin
 * @property string $name
 * @property string $surname
 * @property string $father
 * @property Carbon|null $dob
 * @property string $email
 * @property UserStatus|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'company_users';

    protected $keyType = 'string';

    protected $primaryKey = 'fin';

    protected $casts = [
        'status' => UserStatus::class,
        'dob' => 'date:Y-m-d',
    ];

    protected $fillable = [
        'fin',
        'name',
        'surname',
        'father',
        'dob',
        'email',
        'status',
    ];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
