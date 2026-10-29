<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'mrp_price' => 'decimal:4',
        'wholesale_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'reorder_level' => 'decimal:4',
        'is_tax_inclusive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
