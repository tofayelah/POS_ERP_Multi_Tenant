<?php

namespace App\Filament\Pages;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Subledger\Contracts\SubledgerAgingContract;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AgingRegisterPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Aging register';

    protected ?string $heading = 'Accounts Receivable & Payable Aging Register';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance Accounting';

    protected string $view = 'filament.pages.aging-register-page';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = TenantContext::get();
        $company = Company::where('tenant_id', $tenant?->id)->first();

        $arAging = null;
        $apAging = null;

        if ($company) {
            /** @var SubledgerAgingContract $agingService */
            $agingService = app(SubledgerAgingContract::class);
            $arAging = $agingService->getReceivablesAging($company->id);
            $apAging = $agingService->getPayablesAging($company->id);
        }

        return [
            'company' => $company,
            'arAging' => $arAging,
            'apAging' => $apAging,
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
