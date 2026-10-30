<?php

namespace App\Providers\Filament;

use App\Core\Http\Middleware\FilamentTenantContextMiddleware;
use App\Core\Http\Middleware\SetLocaleMiddleware;
use App\Core\Http\Middleware\VerifySupportAccessActive;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->login()
            ->tenant(Tenant::class, slugAttribute: 'subdomain')
            ->tenantMiddleware([
                FilamentTenantContextMiddleware::class,
                VerifySupportAccessActive::class,
            ])
            ->brandName('KC ERP')
            ->colors([
                'primary' => Color::Sky,
            ])
            ->navigationGroups([
                'POS',
                'Product Master',
                'Procurement',
                'Inventory Management',
                'CRM & Sales',
                'E-Commerce',
                'HRM & Workforce',
                'Finance Accounting',
                'Tax & VAT Compliance',
                'Executive BI & Management Intelligence',
                'Organization Company',
                'Administration',
                'System Settings',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->userMenuItems([
                'language_en' => MenuItem::make()
                    ->label('English')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => 'en'])),
                'language_bn' => MenuItem::make()
                    ->label('বাংলা')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => 'bn'])),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocaleMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
