<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter Plan',
                'description' => 'Single-store starter plan for small retail shops',
                'price_bdt' => '2500.00',
                'billing_interval' => 'monthly',
                'max_branches' => 1,
                'max_terminals' => 1,
                'max_users' => 3,
                'included_modules' => ['core', 'accounting', 'tax', 'sales', 'pos'],
                'is_active' => true,
            ]
        );

        Plan::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Retail Plan',
                'description' => 'Multi-branch retail management with NBR VAT and inventory',
                'price_bdt' => '5000.00',
                'billing_interval' => 'monthly',
                'max_branches' => 3,
                'max_terminals' => 5,
                'max_users' => 10,
                'included_modules' => ['core', 'accounting', 'tax', 'sales', 'pos', 'inventory', 'procurement'],
                'is_active' => true,
            ]
        );

        Plan::firstOrCreate(
            ['code' => 'enterprise'],
            [
                'name' => 'Enterprise Plan',
                'description' => 'Unlimited outlets with advanced reporting and warehouse operations',
                'price_bdt' => '15000.00',
                'billing_interval' => 'monthly',
                'max_branches' => 20,
                'max_terminals' => 50,
                'max_users' => 100,
                'included_modules' => ['core', 'accounting', 'tax', 'sales', 'pos', 'inventory', 'procurement', 'reporting', 'catalog'],
                'is_active' => true,
            ]
        );
    }
}
