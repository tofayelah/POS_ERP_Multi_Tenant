<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLocationScope extends Model
{
    use HasUuids;

    protected $table = 'user_location_scopes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'membership_id',
        'company_id',
        'branch_id',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'membership_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
