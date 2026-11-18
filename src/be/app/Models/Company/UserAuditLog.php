<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

/**
 * An immutable, append-only audit row describing one change to a
 * company_users record. Deliberately has no relationship/foreign key back
 * to App\Models\Company\User -- a row must remain readable after the user
 * it describes has been hard-deleted.
 */
class UserAuditLog extends Model
{
    /**
     * Rows are never updated after insert, and the table has no
     * updated_at column -- only created_at, populated by the DB column
     * default (see the migration). Disabling Eloquent's own timestamp
     * management avoids it trying to write an updated_at that doesn't
     * exist.
     */
    public $timestamps = false;

    protected $table = 'company_user_audit_logs';

    protected $fillable = [
        'fin',
        'action',
        'changes',
        'performed_by',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];
}
