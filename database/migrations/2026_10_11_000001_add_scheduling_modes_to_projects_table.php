<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode akhir pekan dan hari libur untuk perhitungan hari kerja.
     *
     * weekend_mode: 'skip' = Sabtu-Minggu dilewati, 'work' = tetap jalan.
     * holiday_mode: 'skip' = tanggal di tabel holidays dilewati, 'work' = tetap jalan.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'weekend_mode')) {
                $table->string('weekend_mode', 10)->default('skip');
            }

            if (!Schema::hasColumn('projects', 'holiday_mode')) {
                $table->string('holiday_mode', 10)->default('skip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'weekend_mode')) {
                $table->dropColumn('weekend_mode');
            }

            if (Schema::hasColumn('projects', 'holiday_mode')) {
                $table->dropColumn('holiday_mode');
            }
        });
    }
};
