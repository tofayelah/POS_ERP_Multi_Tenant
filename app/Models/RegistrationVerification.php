<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $email
 * @property string $phone
 * @property string $email_token
 * @property Carbon|null $email_verified_at
 * @property string $phone_otp_hash
 * @property string|null $raw_otp
 * @property Carbon|null $phone_verified_at
 * @property array<string, mixed> $payload
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RegistrationVerification extends Model
{
    use HasUuids;

    protected $table = 'registration_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'phone',
        'email_token',
        'email_verified_at',
        'phone_otp_hash',
        'raw_otp',
        'phone_verified_at',
        'payload',
        'expires_at',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
