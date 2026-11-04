<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Contracts\StockReservationServiceContract;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Release expired inventory reservations and restore available warehouse stock';

    public function handle(StockReservationServiceContract $service): int
    {
        $released = $service->releaseExpiredReservations();
        $this->info("Released {$released} expired inventory reservations.");

        return self::SUCCESS;
    }
}
