<?php

namespace App\Filament\Resources\Pembelians\Tables;

use App\Exceptions\StokTidakCukupException;
use App\Services\StokService;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;

class PembeliansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomer_entry')
                    ->label('Nomer Entry')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('supplier.nama_supplier')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('jenis_pembayaran')
                    ->label('Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tunai' => 'success',
                        'transfer' => 'info',
                        'tempo' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Diinput oleh'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'nama_supplier'),
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),
                SelectFilter::make('jenis_pembayaran')
                    ->label('Jenis Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer',
                        'tempo' => 'Tempo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function ($record, DeleteAction $action) {
                        $stok = app(StokService::class);
                        try {
                            return DB::transaction(function () use ($stok, $record) {
                                $stok->balikkanPembelian($stok->snapshotPembelian($record));
                                return $record->delete();
                            });
                        } catch (StokTidakCukupException $e) {
                            Notification::make()->danger()
                                ->title('Tidak bisa menghapus')
                                ->body($e->getMessage())
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                // Removed DeleteBulkAction to prevent bypass of stock validation
            ]);
    }
}
