<?php

namespace App\Models;

use App\Core\Tenancy\TenantContext;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->wherePivot('is_active', true);
    }

    /**
     * Retrieve active location scopes for the active tenant.
     *
     * @return Collection<int, UserLocationScope>
     */
    public function activeLocationScopes(): Collection
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return new Collection;
        }

        /** @var TenantMembership|null $membership */
        $membership = $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            return new Collection;
        }

        return $membership->locationScopes;
    }

    /**
     * Check if user is authorized to view or manage a company and/or branch.
     */
    public function canAccessLocation(?string $companyId, ?string $branchId = null): bool
    {
        if ($this->hasRole(['Tenant Owner', 'Tenant Admin', 'Accountant', 'Auditor'])) {
            return true;
        }

        $scopes = $this->activeLocationScopes();

        foreach ($scopes as $scope) {
            if ($scope->company_id === $companyId) {
                // If scope branch is null, user has company-wide access
                if ($scope->branch_id === null) {
                    return true;
                }
                // If specific branch is requested, match
                if ($branchId !== null && $scope->branch_id === $branchId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->memberships()->where('is_active', true)->exists();
        }

        return false;
    }

    /**
     * @return array<Model>|Collection<int, Tenant>
     */
    public function getTenants(Panel $panel): array|Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->memberships()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->exists();
    }
}
