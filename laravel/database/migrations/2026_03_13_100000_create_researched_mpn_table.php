<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('researched_mpn', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 512)->index();
            $table->string('source', 32); // 'digikey', 'mouser', 'gemini'
            $table->json('response_json');
            $table->timestamps();
            $table->unique(['cache_key', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('researched_mpn');
    }
};
