<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pola pengulangan hari libur.
     *
     * none   = libur satu kali (hanya pada tanggal di kolom date)
     * yearly = berulang setiap tahun (bulan + tanggal sama)
     * monthly= berulang setiap bulan (tanggal sama)
     * weekly = berulang setiap minggu (hari dalam minggu sama)
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('holidays', 'repeats')) {
                $table->string('repeats', 10)->default('none');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'repeats')) {
                $table->dropColumn('repeats');
            }
        });
    }
};
