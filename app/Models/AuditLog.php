<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property int|null $chain_position
 * @property string $table_name
 * @property string $record_id
 * @property string $action
 * @property string $actor_id
 * @property string $actor_type
 * @property string|null $client_ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string $payload_hash
 * @property string|null $prev_hash
 * @property string|null $current_hash
 * @property string $status
 * @property Carbon|null $hashed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 */
class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'chain_position',
        'table_name',
        'record_id',
        'action',
        'actor_id',
        'actor_type',
        'client_ip',
        'user_agent',
        'old_values',
        'new_values',
        'payload_hash',
        'prev_hash',
        'current_hash',
        'status',
        'hashed_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chain_position' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'hashed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
