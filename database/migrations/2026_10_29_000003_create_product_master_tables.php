<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Units of Measure (UOM)
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->string('name', 128); // Pieces, Kilogram, Liter, Box
            $table->string('code', 32);  // PCS, KG, LTR, BOX
            $table->string('symbol', 16)->nullable();
            $table->boolean('allow_decimal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_uom_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
        });

        // 2. Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->string('name', 255);
            $table->string('code', 64)->nullable();
            $table->string('origin_country', 2)->default('BD');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_brands_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
        });

        // 3. Categories (Self-referencing hierarchy)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->uuid('parent_id')->nullable();
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_categories_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
            $table->foreign(['tenant_id', 'parent_id'])->references(['tenant_id', 'id'])->on('categories')->nullOnDelete();
        });

        // 4. Products Master
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->string('code', 64)->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('brand_id')->nullable();
            $table->uuid('uom_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('has_variants')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_products_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
            $table->foreign(['tenant_id', 'category_id'])->references(['tenant_id', 'id'])->on('categories')->nullOnDelete();
            $table->foreign(['tenant_id', 'brand_id'])->references(['tenant_id', 'id'])->on('brands')->nullOnDelete();
            $table->foreign(['tenant_id', 'uom_id'])->references(['tenant_id', 'id'])->on('units_of_measure')->nullOnDelete();
            $table->foreign(['tenant_id', 'supplier_id'])->references(['tenant_id', 'id'])->on('suppliers')->nullOnDelete();
        });

        // 5. Product Variants (SKUs, Barcodes, Pricing, VAT)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->uuid('product_id');
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->string('variant_name', 255)->nullable();
            
            // Monetary figures in DECIMAL(18, 4)
            $table->decimal('purchase_price', 18, 4)->default(0.0000);
            $table->decimal('mrp_price', 18, 4)->default(0.0000);
            $table->decimal('wholesale_price', 18, 4)->default(0.0000);

            // VAT & Tax
            $table->decimal('tax_rate', 5, 2)->default(5.00); // 5% retail VAT
            $table->boolean('is_tax_inclusive')->default(true);
            $table->decimal('reorder_level', 12, 4)->default(10.0000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_product_variants_composite');
            $table->unique(['tenant_id', 'sku'], 'uq_variants_tenant_sku');
            $table->index(['tenant_id', 'barcode'], 'idx_variants_tenant_barcode');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('units_of_measure');
    }
};
