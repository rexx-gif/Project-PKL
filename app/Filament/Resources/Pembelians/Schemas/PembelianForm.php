<?php

namespace App\Filament\Resources\Pembelians\Schemas;

use App\Models\Barang;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PembelianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // === MASTER BELI ===
                TextInput::make('nomer_entry')
                    ->label('Nomer Entry (Master)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpan(1),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->required()
                    ->columnSpan(1),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'nama_supplier')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),
                Select::make('gudang_id')
                    ->label('Gudang Utama')
                    ->relationship('gudang', 'nama_gudang')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),
                Select::make('jenis_pembayaran')
                    ->label('Jenis Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer',
                        'tempo' => 'Tempo',
                    ])
                    ->required()
                    ->columnSpan(2),

                // === DETAIL BELI ===
                Repeater::make('details')
                    ->label('Detail Barang')
                    ->relationship()
                    ->schema([
                        Select::make('barang_id')
                            ->label('Barang')
                            ->relationship('barang', 'nama_barang')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $barang = Barang::find($state);
                                    if ($barang) {
                                        $set('harga', $barang->harga_beli);
                                    }
                                }
                            })
                            ->columnSpan(3),
                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->relationship('gudang', 'nama_gudang')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(3),
                        TextInput::make('satuan')
                            ->label('Satuan')
                            ->default('pcs')
                            ->columnSpan(2),
                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::hitungSubtotal($get, $set);
                            })
                            ->columnSpan(2),
                        TextInput::make('harga')
                            ->label('Harga')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('Rp')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::hitungSubtotal($get, $set);
                            })
                            ->columnSpan(2),
                        TextInput::make('diskon')
                            ->label('Diskon (%)')
                            ->numeric()
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state ?? 0)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::hitungSubtotal($get, $set);
                            })
                            ->columnSpan(3),
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->readOnly()
                            ->columnSpan(3),
                    ])
                    ->columns(6)
                    ->addActionLabel('+ Tambah Barang')
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columnSpanFull(),

                // === TOTAL ===
                TextInput::make('total')
                    ->label('Total Pembelian')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->readOnly()
                    ->columnSpan(1),
                TextInput::make('diskon')
                    ->label('Diskon Keseluruhan')
                    ->numeric()
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => $state ?? 0)
                    ->prefix('Rp')
                    ->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $total = (int) $get('total');
                        $diskon = (int) $get('diskon');
                        $set('neto', $total - $diskon);
                    })
                    ->columnSpan(1),
                TextInput::make('neto')
                    ->label('Neto (Bersih)')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->readOnly()
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    // Hitung subtotal per item: (jumlah * harga) - diskon%
    private static function hitungSubtotal(Get $get, Set $set): void
    {
        $jumlah = (int) $get('jumlah');
        $harga = (int) $get('harga');
        $diskon = (int) $get('diskon');

        $subtotal = $jumlah * $harga;
        if ($diskon > 0) {
            $subtotal = $subtotal - ($subtotal * $diskon / 100);
        }
        $set('subtotal', (int) $subtotal);
    }
}
