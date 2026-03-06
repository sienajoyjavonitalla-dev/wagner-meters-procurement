<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('internal_part_number')->index();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('lifecycle_status')->nullable();
            $table->foreignId('data_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
