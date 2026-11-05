<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property string $id
 * @property string|null $platform_user_id
 * @property string $actor_name
 * @property string $actor_email
 * @property string $action
 * @property string $resource_type
 * @property string $resource_id
 * @property string|null $resource_description
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $client_ip
 * @property string|null $user_agent
 * @property string $status
 * @property string|null $error_message
 * @property string $payload_hash
 * @property int $key_version
 * @property Carbon $created_at
 * @property-read PlatformUser|null $platformUser
 */
class PlatformAuditLog extends Model
{
    use HasUuids;

    protected $table = 'platform_audit_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'platform_user_id',
        'actor_name',
        'actor_email',
        'action',
        'resource_type',
        'resource_id',
        'resource_description',
        'old_values',
        'new_values',
        'reason',
        'client_ip',
        'user_agent',
        'status',
        'error_message',
        'payload_hash',
        'key_version',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'key_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Platform audit logs are strictly immutable and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Platform audit logs cannot be deleted from the audit ledger.');
        });
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }
}
