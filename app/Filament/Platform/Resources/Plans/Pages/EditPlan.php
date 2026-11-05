<?php

namespace App\Filament\Platform\Resources\Plans\Pages;

use App\Core\Platform\Services\PlatformAuditService;
use App\Filament\Platform\Resources\Plans\PlanResource;
use App\Models\Plan;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    /**
     * @var array<string, mixed>
     */
    protected array $originalAttributes = [];

    protected function beforeSave(): void
    {
        /** @var Plan $plan */
        $plan = $this->record;
        $this->originalAttributes = $plan->getOriginal();
    }

    protected function afterSave(): void
    {
        /** @var Plan $plan */
        $plan = $this->record;

        app(PlatformAuditService::class)->logPlanUpdate(
            $plan,
            oldValues: $this->originalAttributes,
            newValues: $plan->toArray()
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
