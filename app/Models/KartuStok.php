<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuStok extends Model
{
    use HasFactory;

    protected $table = 'kartu_stok';

    protected $fillable = [
        'barang_id',
        'gudang_id',
        'nomer_entry',
        'tanggal',
        'keterangan',
        'jenis_transaksi',
        'jumlah',
        'harga',
        'saldo',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
