<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->date('transaction_date')->nullable();
            $table->string('item_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('fiscal_period')->nullable();
            $table->string('fiscal_year')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('source_id')->nullable();
            $table->string('type')->nullable();
            $table->string('application_id')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('ext_cost', 14, 4)->nullable();
            $table->string('comments')->nullable();
            $table->string('product_line')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('contact')->nullable();
            $table->string('address')->nullable();
            $table->string('region')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('research_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
