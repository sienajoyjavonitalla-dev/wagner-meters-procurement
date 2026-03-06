<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_spreads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->string('internal_part_number')->index(); // Item ID from CSV
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_spreads');
    }
};
