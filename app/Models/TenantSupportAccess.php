<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSupportAccess extends Model
{
    use HasUuids;

    protected $table = 'tenant_support_accesses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'platform_user_id',
        'granted_by_user_id',
        'ticket_reference',
        'granted_at',
        'expires_at',
        'is_read_only',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'is_read_only' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isValid(): bool
    {
        return now()->lt($this->expires_at) && is_null($this->revoked_at);
    }
}
