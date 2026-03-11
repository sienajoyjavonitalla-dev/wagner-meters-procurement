<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_runs', function (Blueprint $table) {
            $table->boolean('use_gemini')->default(true)->after('use_claude');
            $table->unsignedInteger('gemini_hits')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('research_runs', function (Blueprint $table) {
            $table->dropColumn(['use_gemini', 'gemini_hits']);
        });
    }
};
