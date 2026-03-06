<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('mpn'); // manufacturer part number(s), pipe-separated if multiple
            $table->string('manufacturer')->nullable();
            $table->string('mapping_status')->nullable(); // mapped, needs_review, non_catalog
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('lookup_mode')->nullable(); // catalog_lookup, non_catalog
            $table->foreignId('data_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mappings');
    }
};
