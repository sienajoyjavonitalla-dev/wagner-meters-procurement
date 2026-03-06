<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g. inventory, vendor_priority, item_spread, mpn_map
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('file_names')->nullable(); // array of uploaded file names
            $table->json('row_counts')->nullable(); // e.g. {"inventory": 1000, "vendors": 50}
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
