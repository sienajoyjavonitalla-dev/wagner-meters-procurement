<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_tasks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('notes');
            $table->decimal('spend_12m', 14, 4)->nullable()->after('description');
            $table->decimal('qty_12m', 14, 4)->nullable()->after('spend_12m');
            $table->decimal('avg_unit_cost_12m', 14, 4)->nullable()->after('qty_12m');
        });
    }

    public function down(): void
    {
        Schema::table('research_tasks', function (Blueprint $table) {
            $table->dropColumn(['description', 'spend_12m', 'qty_12m', 'avg_unit_cost_12m']);
        });
    }
};
