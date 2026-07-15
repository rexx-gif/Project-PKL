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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class PembelianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // === MASTER BELI (Header) ===
                Section::make('Master Pembelian')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nomer_entry')
                                    ->label('Nomer Entry')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                DatePicker::make('tanggal')
                                    ->label('Tanggal')
                                    ->default(now())
                                    ->required(),
                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->relationship('supplier', 'nama_supplier')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('gudang_id')
                                    ->label('Gudang')
                                    ->relationship('gudang', 'nama_gudang')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('jenis_pembayaran')
                                    ->label('Jenis Pembayaran')
                                    ->options([
                                        'tunai' => 'Tunai',
                                        'transfer' => 'Transfer',
                                        'tempo' => 'Tempo',
                                    ])
                                    ->required(),
                            ]),
                    ]),

                // === DETAIL BELI (Items) ===
                Section::make('Detail Barang')
                    ->schema([
                        Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Grid::make(6)
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
                                            ->columnSpan(2),
                                        Select::make('gudang_id')
                                            ->label('Gudang')
                                            ->relationship('gudang', 'nama_gudang')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(1),
                                        TextInput::make('satuan')
                                            ->label('Satuan')
                                            ->default('pcs')
                                            ->columnSpan(1),
                                        TextInput::make('jumlah')
                                            ->label('Jumlah')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->reactive()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                self::hitungSubtotal($get, $set);
                                            })
                                            ->columnSpan(1),
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
                                            ->columnSpan(1),
                                    ]),
                                Grid::make(6)
                                    ->schema([
                                        TextInput::make('diskon')
                                            ->label('Diskon (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                self::hitungSubtotal($get, $set);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('Rp')
                                            ->readOnly()
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->columns(6)
                            ->addActionLabel('+ Tambah Barang')
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                // === TOTAL ===
                Section::make('Ringkasan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->readOnly(),
                                TextInput::make('diskon')
                                    ->label('Diskon')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $total = (int) $get('total');
                                        $diskon = (int) $get('diskon');
                                        $set('neto', $total - $diskon);
                                    }),
                                TextInput::make('neto')
                                    ->label('Neto')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->readOnly(),
                            ]),
                    ]),
            ]);
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
