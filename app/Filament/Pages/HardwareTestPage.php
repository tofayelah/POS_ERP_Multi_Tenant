<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class HardwareTestPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'Hardware & Print Test';

    protected ?string $heading = 'POS Hardware, ESC/POS & Fiscal Bridge Test Bench';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings & Hardware';

    protected string $view = 'filament.pages.hardware-test-page';

    /**
     * Strictly available only in local and staging environments (§11.3, Prompt 10C).
     */
    public static function canAccess(): bool
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            return false;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Shift Supervisor',
        ]);
    }

    /**
     * Register navigation only in local and staging environments.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return app()->environment(['local', 'staging', 'testing']);
    }

    /**
     * Basket vector data with long Bengali product names and provisional footer.
     *
     * @return array<string, mixed>
     */
    public function getReceiptData(): array
    {
        return [
            'store_name' => 'SONAR BANGLA MART',
            'outlet_name' => 'Dhanmondi Outlet #1',
            'terminal_code' => 'T-01',
            'invoice_number' => 'INV-DHA01-T01-TEST01',
            'date_time' => now()->format('d/m/Y h:i A'),
            'mushak_title' => 'গণপ্রজাতন্ত্রী বাংলাদেশ সরকার - জাতীয় রাজস্ব বোর্ড (মূসক ৬.৩)',
            'customer_name' => 'Walk-in Retail Customer',
            'lines' => [
                [
                    'name_bn' => 'খাঁটি ঘানি-ভাঙা সরিষার তেল ১ লিটার বোতল (স্পেশাল গ্রেড)',
                    'name_en' => 'Pure Mustard Oil 1L (Special Grade)',
                    'qty' => 1,
                    'unit_price' => '500.00',
                    'gross' => '500.00',
                    'tax_rate' => '5%',
                    'net' => '476.19',
                    'vat' => '23.81',
                ],
                [
                    'name_bn' => 'আরোগ্য নিম ও তুলসী জীবাণুনাশক ভেষজ সাবান ১২৫ গ্রাম',
                    'name_en' => 'Arogya Neem & Tulsi Herbal Soap 125g',
                    'qty' => 2,
                    'unit_price' => '180.00',
                    'gross' => '360.00',
                    'tax_rate' => '15%',
                    'net' => '313.04',
                    'vat' => '46.96',
                ],
            ],
            'discount' => [
                'type' => 'Cashier Promo Discount (5%)',
                'amount' => '43.00',
            ],
            'totals' => [
                'gross_subtotal' => '860.00',
                'discount_amount' => '43.00',
                'net_subtotal' => '789.23',
                'vat_total' => '70.77',
                'total_payable' => '817.00',
            ],
            'provisional_footer' => [
                'disclaimer' => '*** PROVISIONAL TAX INVOICE - PENDING FISCALIZATION ***',
                'legal_notice' => 'SDC Device Offline / Unresponsive. Statutory Mushak 6.3 fiscal signature will be assigned upon automatic SDC sync.',
                'warning' => 'This receipt constitutes a provisional acknowledgment under NBR retail guidelines.',
            ],
        ];
    }
}
