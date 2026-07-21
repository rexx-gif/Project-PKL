<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pembelian', function (Blueprint $table) {
            $table->dateTime('tanggal')->change();
        });
        Schema::table('perpindahan_barang', function (Blueprint $table) {
            $table->dateTime('tanggal')->change();
        });
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dateTime('tanggal')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pembelian', function (Blueprint $table) {
            $table->date('tanggal')->change();
        });
        Schema::table('perpindahan_barang', function (Blueprint $table) {
            $table->date('tanggal')->change();
        });
        Schema::table('penjualan', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->change();
        });
    }
};
