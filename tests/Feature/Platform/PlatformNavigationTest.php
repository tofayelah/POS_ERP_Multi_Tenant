<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\AuditLogs\PlatformAuditLogResource;
use App\Filament\Platform\Resources\Plans\PlanResource;
use App\Filament\Platform\Resources\Subscriptions\TenantSubscriptionResource;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\PlatformUser;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformNavigationTest extends TestCase
{
    protected PlatformUser $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = PlatformUser::firstOrCreate(
            ['email' => 'admin.test@kcerp.local'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Super Admin Test',
                'password' => bcrypt('Password123!'),
                'role' => 'super_admin',
            ]
        );
    }

    public function test_platform_panel_configuration_has_six_navigation_groups_and_full_width(): void
    {
        $panel = Filament::getPanel('platform');

        $this->assertNotNull($panel);
        $this->assertSame('platform', $panel->getId());
        $this->assertSame('platform', $panel->getPath());
        $this->assertSame('platform', $panel->getAuthGuard());
        $this->assertSame(Width::Full, $panel->getMaxContentWidth());
        $this->assertTrue($panel->isSidebarCollapsibleOnDesktop());

        $groups = $panel->getNavigationGroups();
        $groupLabels = array_map(function ($group) {
            return $group instanceof NavigationGroup ? $group->getLabel() : (string) $group;
        }, $groups);

        $expectedGroups = [
            'SAAS MANAGEMENT',
            'COMMERCIAL',
            'PLATFORM OPERATIONS',
            'SECURITY & GOVERNANCE',
            'CUSTOMER SUCCESS',
            'SYSTEM SETTINGS',
        ];

        foreach ($expectedGroups as $expected) {
            $this->assertContains($expected, $groupLabels, "Navigation group [{$expected}] must be registered in Platform panel.");
        }
    }

    public function test_resources_are_mapped_to_correct_navigation_groups(): void
    {
        $this->assertSame('SAAS MANAGEMENT', TenantResource::getNavigationGroup());
        $this->assertSame(1, TenantResource::getNavigationSort());

        $this->assertSame('COMMERCIAL', PlanResource::getNavigationGroup());
        $this->assertSame(1, PlanResource::getNavigationSort());

        $this->assertSame('COMMERCIAL', TenantSubscriptionResource::getNavigationGroup());
        $this->assertSame(2, TenantSubscriptionResource::getNavigationSort());

        $this->assertSame('SECURITY & GOVERNANCE', PlatformAuditLogResource::getNavigationGroup());
        $this->assertSame(1, PlatformAuditLogResource::getNavigationSort());
    }

    public function test_platform_user_can_access_platform_panel(): void
    {
        $this->assertTrue($this->superAdmin->canAccessPanel(Filament::getPanel('platform')));

        $response = $this->actingAs($this->superAdmin, 'platform')->get('/platform');
        $response->assertSuccessful();
    }

    public function test_unauthenticated_user_redirected_to_platform_login(): void
    {
        $response = $this->get('/platform');
        $response->assertRedirect('/platform/login');
    }
}
