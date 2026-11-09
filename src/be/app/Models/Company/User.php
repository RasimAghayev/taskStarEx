<?php

namespace App\Models\Company;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return \Database\Factories\Company\UserFactory::new();
    }
}
