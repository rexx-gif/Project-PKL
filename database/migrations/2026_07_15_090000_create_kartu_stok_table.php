<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('nomer_entry')->nullable();
            $table->dateTime('tanggal')->nullable();
            $table->string('keterangan')->nullable();
            $table->enum('jenis_transaksi', ['masuk', 'keluar'])->nullable();
            $table->integer('jumlah')->default(0);
            $table->integer('harga')->default(0);
            $table->integer('saldo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_stok');
    }
};
