<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisBarang extends Model
{
    use HasFactory;

    protected $table = 'jenis_barang'; //maksa Laravel ngebaca tabel yang bener dan bukan jenis_barangs (plural bahasa Inggris)
    protected $guarded = ['id']; //biar aman, data id gak bisa diubah

    public function barangs()   //pake 's' buat nandain kalau isinya jamak/banyak
    {
        // Ini artinya Satu-ke-Banyak (One-to-Many).
        // Logikanya: Satu kategori (misal: Minuman) pasti memiliki banyak (hasMany) barang
        // (misal: Kopi Susu, Teh Tarik, Matcha).
        return $this->hasMany(
            // ngasih tau Laravel buat nyari data di model Barang,
            Barang::class,
            // dan nyocokin datanya pake kolom kunci jenis_barang_id.
            'jenis_barang_id'
        );
    }
}
