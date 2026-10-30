<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $setting_id
 * @property string $key
 * @property string $scope_type
 * @property string|null $scope_id
 * @property mixed $old_value
 * @property mixed $new_value
 * @property string|null $changed_by_user_id
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class SettingAuditLog extends Model
{
    use HasUuids;

    protected $table = 'setting_audit_logs';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'setting_id',
        'key',
        'scope_type',
        'scope_id',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Setting, $this>
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'setting_id');
    }
}
