<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'POS Test Tenant',
        'subdomain' => 'pos-'.Str::random(6),
        'status' => 'active',
        'data_residency_region' => 'dhaka-1',
    ]);

    TenantContext::set($this->tenant);

    $this->company = Company::create([
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'POS Test Retail Ltd',
        'bin_number' => '998877665-0101',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard',
    ]);

    $this->branch = Branch::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'name' => 'Mirpur Branch',
        'code' => 'BR-MIR-'.Str::random(4),
        'is_active' => true,
    ]);

    $this->cashier = User::create([
        'name' => 'POS Cashier',
        'email' => 'cashier-'.Str::random(6).'@postest.com',
        'password' => Hash::make('Password123!'),
    ]);

    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'is_active' => true,
    ]);
});

test('can create pos terminal under branch', function () {
    $terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-001',
        'name' => 'Main POS Register 1',
        'is_active' => true,
    ]);

    expect($terminal->id)->not->toBeNull()
        ->and($terminal->terminal_code)->toBe('T-001')
        ->and($terminal->branch->id)->toBe($this->branch->id)
        ->and($terminal->company->id)->toBe($this->company->id);
});

test('duplicate terminal code in same branch is rejected', function () {
    PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-DUP',
        'name' => 'First Reg',
    ]);

    expect(fn () => PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-DUP',
        'name' => 'Second Reg',
    ]))->toThrow(QueryException::class);
});

test('can open and close a pos shift with exact money strings', function () {
    $terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-002',
        'name' => 'Register 2',
    ]);

    $shift = PosShift::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_id' => $terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_cash' => '1000.0000',
    ]);

    expect($shift->status)->toBe('open')
        ->and($shift->opening_cash)->toBe('1000.0000');

    // Close the shift
    $shift->update([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_cash' => '15450.5000',
        'expected_cash' => '15450.5000',
    ]);

    $shift->refresh();
    expect($shift->status)->toBe('closed')
        ->and($shift->closing_cash)->toBe('15450.5000')
        ->and($shift->closed_at)->not->toBeNull();
});

test('invalid shift status is rejected by check constraint', function () {
    $terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-003',
        'name' => 'Register 3',
    ]);

    expect(fn () => PosShift::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_id' => $terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'invalid_status',
        'opened_at' => now(),
        'opening_cash' => '0.0000',
    ]))->toThrow(QueryException::class);
});

test('pos shift with mismatched company branch reference is rejected', function () {
    // Create another company in same tenant
    $company2 = Company::create([
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'Second Company Ltd',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard',
    ]);

    $terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'T-004',
        'name' => 'Register 4',
    ]);

    // Try to associate shift with company2 but branch from company1
    expect(fn () => PosShift::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $company2->id,
        'branch_id' => $this->branch->id, // belongs to company 1
        'terminal_id' => $terminal->id,
        'user_id' => $this->cashier->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_cash' => '0.0000',
    ]))->toThrow(QueryException::class);
});
