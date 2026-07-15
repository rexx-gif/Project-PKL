<?php

namespace App\Filament\Resources\KartuStoks\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class KartuStoksTable
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
                TextColumn::make('nomer_entry')
                    ->label('Nomer Entry')
                    ->searchable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30),
                TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'masuk' => 'success',
                        'keluar' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR'),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('barang_id')
                    ->label('Barang')
                    ->relationship('barang', 'nama_barang'),
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),
                SelectFilter::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options([
                        'masuk' => 'Masuk',
                        'keluar' => 'Keluar',
                    ]),
            ]);
    }
}
