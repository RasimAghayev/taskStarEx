<?php

namespace App\Models\Company;

use App\Enums\UserStatus;
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
        'dob'=>"datetime:Y-m-d"
    ];
    protected $guarded=[];
    protected $fillable=[
        "fin",
        "name",
        "surname",
        "father",
        "dob",
        "email",
        "status",
    ];
}
