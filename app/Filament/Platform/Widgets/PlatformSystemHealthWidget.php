<?php

namespace App\Filament\Platform\Widgets;

use App\Core\Platform\Services\PlatformTelemetryService;
use Filament\Widgets\Widget;

class PlatformSystemHealthWidget extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /**
     * @var view-string
     */
    protected string $view = 'filament.platform.widgets.system-health';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $telemetry = app(PlatformTelemetryService::class);

        $postgres = $telemetry->getPostgresProbe();
        $rls = $telemetry->getRlsCompliance();
        $redis = $telemetry->getRedisProbe();
        $queue = $telemetry->getQueueProbe();
        $bridge = $telemetry->getGoBridgeProbe();
        $audit = $telemetry->getAuditTrailProbe();

        $allOk = ($postgres['status'] === 'ok' && $rls['status'] === 'ok' && $redis['status'] === 'ok');

        return [
            'postgres' => $postgres,
            'rls' => $rls,
            'redis' => $redis,
            'queue' => $queue,
            'bridge' => $bridge,
            'audit' => $audit,
            'overallStatus' => $allOk ? '100% Operational' : 'Degraded',
            'overallColor' => $allOk ? 'success' : 'danger',
            'checkedAt' => now()->format('H:i:s'),
        ];
    }
}
