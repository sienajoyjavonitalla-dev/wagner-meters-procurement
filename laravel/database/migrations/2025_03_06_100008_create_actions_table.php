<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_task_id')->constrained('research_tasks')->cascadeOnDelete();
            $table->decimal('estimated_savings', 14, 4)->nullable();
            $table->string('action_type')->nullable(); // e.g. pricing_benchmark, alternate_part
            $table->string('approval_status')->default('pending')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
