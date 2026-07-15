<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $table = 'supplier';
    // migration uses the default id() column
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama_supplier',
        'no_telepon',
        'alamat',
        'status'
    ];
}
