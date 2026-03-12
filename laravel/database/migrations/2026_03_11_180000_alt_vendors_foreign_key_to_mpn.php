<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alt_vendors', function (Blueprint $table) {
            $table->unsignedBigInteger('mpn_id')->nullable()->after('id');
        });

        foreach (DB::table('alt_vendors')->get() as $row) {
            $firstMpn = DB::table('mpn')->where('inventory_id', $row->inventory_id)->value('id');
            if ($firstMpn !== null) {
                DB::table('alt_vendors')->where('id', $row->id)->update(['mpn_id' => $firstMpn]);
            }
        }

        Schema::table('alt_vendors', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
            $table->dropColumn('inventory_id');
            $table->foreign('mpn_id')->references('id')->on('mpn')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alt_vendors', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_id')->nullable()->after('id');
        });

        foreach (DB::table('alt_vendors')->get() as $row) {
            $inventoryId = $row->mpn_id ? DB::table('mpn')->where('id', $row->mpn_id)->value('inventory_id') : null;
            if ($inventoryId !== null) {
                DB::table('alt_vendors')->where('id', $row->id)->update(['inventory_id' => $inventoryId]);
            }
        }

        Schema::table('alt_vendors', function (Blueprint $table) {
            $table->dropForeign(['mpn_id']);
            $table->dropColumn('mpn_id');
            $table->foreign('inventory_id')->references('id')->on('inventories')->cascadeOnDelete();
        });
    }
};
