<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index(); // e.g. fx_rates, pipeline_metadata
            $table->json('value_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_snapshots');
    }
};
