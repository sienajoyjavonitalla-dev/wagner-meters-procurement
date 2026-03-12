<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researched_mpn', function (Blueprint $table) {
            $table->string('url', 1024)->nullable()->after('response_json');
        });
    }

    public function down(): void
    {
        Schema::table('researched_mpn', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
