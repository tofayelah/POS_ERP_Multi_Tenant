<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // 1. Auto-assign tenant_id & company_id on creating
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $tenantId = TenantContext::getTenantId();
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }

            if (empty($model->company_id)) {
                $companyId = TenantContext::getCompanyId();
                if ($companyId) {
                    $model->company_id = $companyId;
                }
            }
        });

        // 2. Apply global query scoping
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
