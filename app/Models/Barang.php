<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barang';
    protected $guarded = ['id'];

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'barang_gudang')
            ->withPivot('stok') //biar kita bisa akses stoknya
            ->withTimestamps();
    }
}
