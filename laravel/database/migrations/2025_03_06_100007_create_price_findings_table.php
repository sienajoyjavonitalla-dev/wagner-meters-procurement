<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_task_id')->constrained('research_tasks')->cascadeOnDelete();
            $table->string('provider'); // digikey, mouser, nexar, claude
            $table->string('currency', 3)->nullable();
            $table->json('price_breaks_json')->nullable(); // e.g. [{"qty":1,"price":0.5}, ...]
            $table->decimal('min_unit_price', 14, 4)->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->boolean('accepted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_findings');
    }
};
