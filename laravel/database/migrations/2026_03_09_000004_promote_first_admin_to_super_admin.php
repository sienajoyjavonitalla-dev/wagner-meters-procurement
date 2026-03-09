<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasSuperAdmin = DB::table('users')->where('role', 'super_admin')->exists();
        if ($hasSuperAdmin) {
            return;
        }

        $firstAdmin = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        if ($firstAdmin) {
            DB::table('users')->where('id', $firstAdmin)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'admin']);
    }
};
