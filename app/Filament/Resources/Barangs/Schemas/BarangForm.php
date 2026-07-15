<?php

namespace App\Filament\Resources\Barangs\Schemas;

use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('jenis_barang_id')
                    ->label('Jenis Barang')
                    ->relationship('jenisBarang', 'nama_jenis')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('nama_barang')
                    ->label('Nama Barang')
                    ->required()
                    ->maxLength(255),
                TextInput::make('harga_beli')
                    ->label('Harga Beli')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('harga_jual')
                    ->label('Harga Jual')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->default(0),
            ]);
    }
}
