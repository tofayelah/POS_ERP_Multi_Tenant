<?php

namespace App\Core\Security\Events;

use App\Models\TenantSupportAccess;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportAccessRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TenantSupportAccess $access
    ) {}
}
