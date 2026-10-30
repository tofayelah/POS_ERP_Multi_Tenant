<?php

namespace App\Modules\Accounting\Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Support\Str;

class BangladeshRetailCoaTemplate
{
    /**
     * Seed standard Bangladesh Retail Chart of Accounts for a company.
     *
     * @return array<array-key, ChartOfAccount>
     */
    public function seedForCompany(string $tenantId, string $companyId): array
    {
        $accounts = [
            // Assets (1000)
            [
                'code' => '1010',
                'name_en' => 'Cash in Hand',
                'name_bn' => 'নগদ তহবিল',
                'type' => 'asset',
                'reconcilable' => true,
            ],
            [
                'code' => '1020',
                'name_en' => 'Bank Current Account',
                'name_bn' => 'ব্যাংক চলতি হিসাব',
                'type' => 'asset',
                'reconcilable' => true,
            ],
            [
                'code' => '1030',
                'name_en' => 'Accounts Receivable (Debtors)',
                'name_bn' => 'দেনাদার হিসাব',
                'type' => 'asset',
                'reconcilable' => false,
            ],
            [
                'code' => '1040',
                'name_en' => 'Merchandise Inventory',
                'name_bn' => 'মজুদ পণ্য',
                'type' => 'asset',
                'reconcilable' => false,
            ],
            [
                'code' => '1050',
                'name_en' => 'VAT Current Account / VAT Receivable (Mushak Input Tax)',
                'name_bn' => 'মূসক চলতি হিসাব (উপকরণ কর)',
                'type' => 'asset',
                'reconcilable' => true,
            ],
            [
                'code' => '1060',
                'name_en' => 'VDS Receivable (VAT Deducted at Source by Customers)',
                'name_bn' => 'উৎসে মূসক কর্তন (গ্রাহক কর্তন)',
                'type' => 'asset',
                'reconcilable' => false,
            ],
            [
                'code' => '1070',
                'name_en' => 'Advance Income Tax (AIT)',
                'name_bn' => 'অগ্রিম আয়কর',
                'type' => 'asset',
                'reconcilable' => false,
            ],

            // Liabilities (2000)
            [
                'code' => '2010',
                'name_en' => 'Accounts Payable (Creditors)',
                'name_bn' => 'পাওনাদার হিসাব',
                'type' => 'liability',
                'reconcilable' => false,
            ],
            [
                'code' => '2020',
                'name_en' => 'Output VAT Payable (Mushak Output Tax)',
                'name_bn' => 'প্রদেয় মূসক (উৎপাদন কর)',
                'type' => 'liability',
                'reconcilable' => true,
            ],
            [
                'code' => '2030',
                'name_en' => 'Supplementary Duty (SD) Payable',
                'name_bn' => 'প্রদেয় সম্পূরক শুল্ক',
                'type' => 'liability',
                'reconcilable' => true,
            ],
            [
                'code' => '2040',
                'name_en' => 'VDS Payable (VAT Deducted at Source from Suppliers)',
                'name_bn' => 'প্রদেয় উৎসে মূসক (সরবরাহকারী কর্তন)',
                'type' => 'liability',
                'reconcilable' => true,
            ],
            [
                'code' => '2050',
                'name_en' => 'TDS Payable (Tax Deducted at Source from Suppliers)',
                'name_bn' => 'প্রদেয় উৎসে আয়কর',
                'type' => 'liability',
                'reconcilable' => true,
            ],

            // Equity (3000)
            [
                'code' => '3010',
                'name_en' => "Owner's Capital",
                'name_bn' => 'মালিকের মূলধন',
                'type' => 'equity',
                'reconcilable' => false,
            ],
            [
                'code' => '3020',
                'name_en' => 'Retained Earnings',
                'name_bn' => 'সংরক্ষিত আয়',
                'type' => 'equity',
                'reconcilable' => false,
            ],

            // Revenue (4000)
            [
                'code' => '4010',
                'name_en' => 'Retail Sales Revenue',
                'name_bn' => 'খুচরা বিক্রয় আয়',
                'type' => 'revenue',
                'reconcilable' => false,
            ],
            [
                'code' => '4020',
                'name_en' => 'Wholesale Sales Revenue',
                'name_bn' => 'পাইকারি বিক্রয় আয়',
                'type' => 'revenue',
                'reconcilable' => false,
            ],
            [
                'code' => '4090',
                'name_en' => 'Sales Discounts & Allowances',
                'name_bn' => 'বিক্রয় বাট্টা',
                'type' => 'revenue',
                'reconcilable' => false,
            ],

            // Cost of Goods Sold (5000)
            [
                'code' => '5010',
                'name_en' => 'Cost of Goods Sold - Retail',
                'name_bn' => 'বিক্রিত পণ্যের ব্যয়',
                'type' => 'expense',
                'reconcilable' => false,
            ],
            [
                'code' => '5020',
                'name_en' => 'Inward Freight & Handling',
                'name_bn' => 'পণ্য পরিবহন খরচ',
                'type' => 'expense',
                'reconcilable' => false,
            ],

            // Operating Expenses (6000)
            [
                'code' => '6010',
                'name_en' => 'Salaries & Allowances',
                'name_bn' => 'বেতন ও ভাতাদি',
                'type' => 'expense',
                'reconcilable' => false,
            ],
            [
                'code' => '6020',
                'name_en' => 'Store Rent',
                'name_bn' => 'দোকান ভাড়া',
                'type' => 'expense',
                'reconcilable' => false,
            ],
            [
                'code' => '6030',
                'name_en' => 'Utilities (Electricity & Water)',
                'name_bn' => 'বিদ্যুৎ ও ইউটিলিটি',
                'type' => 'expense',
                'reconcilable' => false,
            ],
            [
                'code' => '6040',
                'name_en' => 'Bank Charges',
                'name_bn' => 'ব্যাংক চার্জ',
                'type' => 'expense',
                'reconcilable' => false,
            ],
        ];

        $created = [];

        foreach ($accounts as $data) {
            $record = ChartOfAccount::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'account_code' => $data['code'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'account_name' => $data['name_en'],
                    'account_name_bn' => $data['name_bn'],
                    'account_type' => $data['type'],
                    'is_reconcilable' => $data['reconcilable'],
                    'is_active' => true,
                ]
            );

            $created[$data['code']] = $record;
        }

        return $created;
    }
}
