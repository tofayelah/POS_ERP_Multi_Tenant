<?php

namespace App\Filament\Platform\Resources\Plans\Pages;

use App\Core\Platform\Services\PlatformAuditService;
use App\Filament\Platform\Resources\Plans\PlanResource;
use App\Models\Plan;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function afterCreate(): void
    {
        /** @var Plan $plan */
        $plan = $this->record;

        app(PlatformAuditService::class)->logPlanCreation($plan);
    }
}
