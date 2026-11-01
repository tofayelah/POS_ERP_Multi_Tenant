<?php

namespace App\Modules\Pos\DTOs;

use Carbon\Carbon;

readonly class SyncBatchRequestDTO
{
    /**
     * @param  array<int, SyncSaleDTO>  $sales
     */
    public function __construct(
        public string $batchId,
        public string $terminalId,
        public string $shiftId,
        public ?Carbon $deviceTimestamp = null,
        public array $sales = [],
    ) {}
}
