<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    use HasFactory;

    protected $table = 'gudang';
    protected $guarded = ['id'];

    public function barangs()
    {
        // Satu lokasi Gudang (misal: Rak Depan) pastinya bisa menampung banyak jenis Barang (Indomie, Kopi, Gula).
        return $this->belongsToMany(Barang::class, 'barang_gudang')
            ->withPivot('stok')
            ->withTimestamps();
    }
}
