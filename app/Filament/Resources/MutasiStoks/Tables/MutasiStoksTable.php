<?php

namespace App\Filament\Resources\MutasiStoks\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class MutasiStoksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('awal')
                    ->label('Stok Awal')
                    ->sortable(),
                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->sortable()
                    ->color('success'),
                TextColumn::make('keluar')
                    ->label('Keluar')
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('barang_id')
                    ->label('Barang')
                    ->relationship('barang', 'nama_barang'),
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),
            ]);
    }
}
