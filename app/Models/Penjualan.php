<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';

    protected $fillable = [
        'nomer_nota',
        'customer_id',
        'gudang_id',
        'tanggal',
        'total',
        'diskon',
        'neto',
        'jenis_pembayaran',
        'bayar',
        'kembalian',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id_customer');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function details()
    {
        return $this->hasMany(DetailJual::class, 'penjualan_id');
    }
}
