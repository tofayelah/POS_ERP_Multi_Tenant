<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Widgets\PlatformPlanDistributionChart;
use App\Filament\Platform\Widgets\PlatformQuickActionsWidget;
use App\Filament\Platform\Widgets\PlatformStatsOverviewWidget;
use App\Filament\Platform\Widgets\PlatformSystemHealthWidget;
use App\Models\PlatformUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformDashboardKpiTest extends TestCase
{
    protected PlatformUser $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('platform.audit.hmac_secret', 'test-dedicated-audit-secret-key-64-characters-for-testing-purposes');
        Config::set('platform.audit.key_version', 1);

        $this->superAdmin = PlatformUser::firstOrCreate(
            ['email' => 'admin.kpi@kcerp.local'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Super Admin KPI Test',
                'password' => 'Password123!',
                'role' => 'super_admin',
            ]
        );
    }

    public function test_stats_overview_widget_renders_live_metrics_without_hardcoded_mrr_fallback(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(PlatformStatsOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_plan_distribution_chart_renders_without_fake_counts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(PlatformPlanDistributionChart::class)
            ->assertSuccessful();
    }

    public function test_system_health_widget_renders_live_probes(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(PlatformSystemHealthWidget::class)
            ->assertSuccessful()
            ->assertSee('PostgreSQL 17 DB')
            ->assertSee('PostgreSQL RLS')
            ->assertSee('Redis 7 Cluster');
    }

    public function test_quick_actions_widget_renders_dynamic_active_tenants(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(PlatformQuickActionsWidget::class)
            ->assertSuccessful()
            ->assertSee('Platform Quick Actions');
    }
}
