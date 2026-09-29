<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('inquiry_no')->unique();
            $table->string('inquiry_type')->default('NEW');
            $table->date('inquiry_date');
            $table->date('customer_need_date')->nullable();
            $table->string('sales_pic');
            $table->string('source');
            $table->string('customer');
            $table->string('product_name_draft');
            $table->string('product_category')->nullable();
            $table->string('product_type');
            $table->string('market_variant_id')->nullable();
            $table->text('sales_notes')->nullable();
            $table->boolean('customer_provides_raw_material')->default(false);
            $table->string('status')->default('DRAFT');
            $table->timestamps();
        });

        Schema::create('inquiry_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->string('part_name');
            $table->string('part_type');
            $table->string('component_type')->default('STANDARD_SINGLE');
            $table->unsignedInteger('qty_per_set')->default(1);
            $table->unsignedInteger('content_per_pack')->default(1);
            $table->decimal('length', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('glue_flap', 6, 2)->default(15.0);
            $table->decimal('bleed', 6, 2)->default(3.0);
            $table->decimal('tuck_flap', 6, 2)->default(15.0);
            $table->string('unit', 5)->default('mm');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inquiry_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->unsignedInteger('tier_number');
            $table->unsignedInteger('qty');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('inquiry_toolings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->string('tooling_type');
            $table->string('status');
            $table->boolean('is_new_cost')->default(true);
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_toolings');
        Schema::dropIfExists('inquiry_tiers');
        Schema::dropIfExists('inquiry_parts');
        Schema::dropIfExists('inquiries');
    }
};
