<?php

namespace App\Filament\Pages;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\PosTerminal;
use App\Models\TaxCode;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class OnboardingWizardPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Onboarding Setup / অনবোর্ডিং সেটআপ';

    protected ?string $heading = 'Tenant Onboarding & Compliance Setup';

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected string $view = 'filament.pages.onboarding-wizard-page';

    /**
     * @return array<string, mixed>
     */
    public function getOnboardingStatus(): array
    {
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $coaCount = ChartOfAccount::where('tenant_id', $tenant->id)->count();
        $taxCodeCount = TaxCode::where('tenant_id', $tenant->id)->count();
        $branchCount = Branch::where('tenant_id', $tenant->id)->count();
        $terminalCount = PosTerminal::where('tenant_id', $tenant->id)->count();
        $fiscalYearCount = FiscalYear::where('tenant_id', $tenant->id)->count();
        $periodCount = AccountingPeriod::where('tenant_id', $tenant->id)->count();

        return [
            'tenant' => $tenant,
            'coa_count' => $coaCount,
            'tax_code_count' => $taxCodeCount,
            'branch_count' => $branchCount,
            'terminal_count' => $terminalCount,
            'fiscal_year_count' => $fiscalYearCount,
            'period_count' => $periodCount,
            'is_fully_configured' => $coaCount > 0 && $taxCodeCount > 0 && $branchCount > 0 && $terminalCount > 0 && $periodCount >= 12,
        ];
    }
}
