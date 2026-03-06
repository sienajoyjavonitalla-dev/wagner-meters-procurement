<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_findings', function (Blueprint $table) {
            $table->string('matched_mpn')->nullable()->after('provider');
        });

        Schema::table('actions', function (Blueprint $table) {
            $table->decimal('priority_score', 14, 4)->nullable()->after('action_type');
        });
    }

    public function down(): void
    {
        Schema::table('price_findings', function (Blueprint $table) {
            $table->dropColumn('matched_mpn');
        });
        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn('priority_score');
        });
    }
};
