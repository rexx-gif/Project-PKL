<?php

use App\Http\Controllers\KasirController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');
Route::post('/kasir/simpan', [KasirController::class, 'simpan'])->name('kasir.simpan');
