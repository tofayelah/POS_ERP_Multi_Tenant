<?php

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\PosTerminal;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Billing\Contracts\OnboardingWizardServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'code' => 'retail_starter_'.Str::random(6),
        'name' => 'Retail Starter',
        'included_modules' => ['accounting', 'tax', 'catalog', 'sales', 'pos'],
    ]);

    $this->wizard = app(OnboardingWizardServiceContract::class);
});

test('onboarding wizard provisions complete tenant and seeds default module data', function () {
    $subdomain = 'wizard-'.Str::random(6);

    $result = $this->wizard->onboard([
        'tenant_name' => 'Wizard Mart',
        'subdomain' => $subdomain,
        'plan_code' => $this->plan->code,
        'company_name' => 'Wizard Mart Ltd',
        'bin_number' => '112233445-0101',
        'branch_name' => 'Main Branch',
        'branch_code' => 'BR-MAIN',
        'terminal_code' => 'T-001',
        'admin_name' => 'Mart Admin',
        'admin_email' => 'admin-'.Str::random(6).'@wizardmart.com',
        'admin_password' => 'Password123!',
    ]);

    $tenant = $result['tenant'];
    $company = $result['company'];
    $branch = $result['branch'];
    $terminal = $result['terminal'];
    $user = $result['user'];

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($company)->toBeInstanceOf(Company::class)
        ->and($branch)->toBeInstanceOf(Branch::class)
        ->and($terminal)->toBeInstanceOf(PosTerminal::class)
        ->and($user)->toBeInstanceOf(User::class);

    // 1. Verify Fiscal Year and 12 Periods seeded
    $fiscalYears = FiscalYear::where('tenant_id', $tenant->id)->get();
    expect($fiscalYears)->toHaveCount(1);

    $periods = AccountingPeriod::where('tenant_id', $tenant->id)->get();
    expect($periods)->toHaveCount(12);

    // 2. Verify Bangladesh Chart of Accounts seeded
    $cashAccount = ChartOfAccount::where('tenant_id', $tenant->id)
        ->where('account_code', '1010')
        ->first();
    $salesAccount = ChartOfAccount::where('tenant_id', $tenant->id)
        ->where('account_code', '4010')
        ->first();
    $vatPayableAccount = ChartOfAccount::where('tenant_id', $tenant->id)
        ->where('account_code', '2020')
        ->first();

    expect($cashAccount)->not->toBeNull()
        ->and($salesAccount)->not->toBeNull()
        ->and($vatPayableAccount)->not->toBeNull();

    // 3. Verify standard VAT codes seeded
    $vatCodes = TaxCode::where('tenant_id', $tenant->id)->pluck('code')->all();
    expect($vatCodes)->toContain('VAT-15', 'VAT-05', 'VAT-00', 'EXEMPT');

    // 4. Verify newly onboarded tenant can immediately post a balanced journal entry
    $openPeriod = $periods->firstWhere('period_number', 1);

    $dto = new JournalEntryDTO(
        companyId: $company->id,
        periodId: $openPeriod->id,
        postingDate: $openPeriod->start_date->format('Y-m-d'),
        businessDate: $openPeriod->start_date->format('Y-m-d'),
        referenceType: 'onboarding_test',
        narration: 'First journal of newly onboarded tenant',
        postedBy: $user->id,
        lines: [
            new JournalLineDTO(
                accountId: $cashAccount->id,
                debitAmount: new Money('1150.00'),
                creditAmount: new Money('0.00'),
                narration: 'Cash received'
            ),
            new JournalLineDTO(
                accountId: $salesAccount->id,
                debitAmount: new Money('0.00'),
                creditAmount: new Money('1000.00'),
                narration: 'Revenue'
            ),
            new JournalLineDTO(
                accountId: $vatPayableAccount->id,
                debitAmount: new Money('0.00'),
                creditAmount: new Money('150.00'),
                narration: 'Output VAT'
            ),
        ]
    );

    $postingService = app(AccountingPostingServiceContract::class);
    $entry = DB::transaction(fn () => $postingService->post($dto));

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->is_posted)->toBeTrue()
        ->and($entry->lines)->toHaveCount(3);
});
