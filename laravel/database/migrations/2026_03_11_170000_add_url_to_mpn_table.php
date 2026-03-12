<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpn', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('mpn', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
