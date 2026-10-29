<?php

namespace App\Core\Scoping;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class LocationScope implements Scope
{
    /**
     * Apply the location scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        // Global administrative and audit roles have tenant-wide scope
        if ($user->hasRole(['Tenant Owner', 'Tenant Admin', 'Accountant', 'Auditor'])) {
            return;
        }

        $scopes = $user->activeLocationScopes();

        // Fail-closed: user without assigned location scopes sees zero records
        if ($scopes->isEmpty()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $branchCol = method_exists($model, 'getLocationScopeBranchColumn')
            ? $model->getLocationScopeBranchColumn()
            : 'branch_id';

        $companyCol = method_exists($model, 'getLocationScopeCompanyColumn')
            ? $model->getLocationScopeCompanyColumn()
            : 'company_id';

        $builder->where(function (Builder $query) use ($scopes, $branchCol, $companyCol) {
            foreach ($scopes as $scope) {
                $query->orWhere(function (Builder $sub) use ($scope, $branchCol, $companyCol) {
                    $sub->where($companyCol, $scope->company_id);

                    if ($scope->branch_id !== null) {
                        $sub->where($branchCol, $scope->branch_id);
                    }
                });
            }
        });
    }
}
