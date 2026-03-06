<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('limit')->default(50);
            $table->boolean('use_claude')->default(true);
            $table->boolean('build_queue')->default(false);
            $table->text('message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_runs');
    }
};
