<?php

namespace App\Filament\Pages;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Contracts\FinancialReportContract;
use App\Modules\Accounting\DTOs\TrialBalanceReportDTO;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TrialBalancePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Trial balance';

    protected ?string $heading = 'General Ledger Trial Balance';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance Accounting';

    protected string $view = 'filament.pages.trial-balance-page';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = TenantContext::get();
        $company = Company::where('tenant_id', $tenant?->id)->first();

        $report = null;
        if ($company) {
            /** @var FinancialReportContract $service */
            $service = app(FinancialReportContract::class);
            $report = $service->getTrialBalance($company->id);
        }

        return [
            'company' => $company,
            'report' => $report,
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Accountant',
            'Auditor',
        ]);
    }
}
