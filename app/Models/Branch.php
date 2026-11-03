<?php

namespace App\Models;

use App\Core\Scoping\HasLocationScope;
use App\Core\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 */
class Branch extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'branches';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            if (empty($branch->code)) {
                $branch->code = static::generateNextCode($branch->tenant_id, $branch->company_id);
            }
        });

        static::updating(function (Branch $branch): void {
            if ($branch->isDirty('code') && $branch->getOriginal('code') !== null) {
                $branch->code = (string) $branch->getOriginal('code');
            }
        });
    }

    /**
     * Auto-generate the next sequential branch code for a company/tenant.
     */
    public static function generateNextCode(?string $tenantId = null, ?string $companyId = null): string
    {
        /** @var Tenant|null $filamentTenant */
        $filamentTenant = Filament::getTenant();

        $tenantId = $tenantId ?? TenantContext::id() ?? $filamentTenant?->id;

        $query = static::withoutGlobalScopes();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        /** @var array<int, string> $existingCodes */
        $existingCodes = $query->pluck('code')->all();

        $padLength = 2;
        $maxNumber = 0;
        foreach ($existingCodes as $code) {
            if (preg_match('/^BR-(\d+)$/i', $code, $matches)) {
                $digits = $matches[1];
                if (strlen($digits) > $padLength) {
                    $padLength = strlen($digits);
                }
                $num = (int) $digits;
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            } elseif (preg_match('/^(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        $format = 'BR-%0'.$padLength.'d';
        $candidate = sprintf($format, $nextNumber);

        while (in_array($candidate, $existingCodes, true)) {
            $nextNumber++;
            $candidate = sprintf($format, $nextNumber);
        }

        return $candidate;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Column on branches table representing the branch identifier for location scoping.
     */
    public function getLocationScopeBranchColumn(): string
    {
        return 'id';
    }
}
