<?php

namespace App\Filament\Resources\PerpindahanBarangs\Tables;

use App\Exceptions\StokTidakCukupException;
use App\Services\StokService;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\DB;

class PerpindahanBarangsTable
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
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('gudangAsal.nama_gudang')
                    ->label('Dari')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gudangTujuan.nama_gudang')
                    ->label('Ke')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('details_count')
                    ->label('Jumlah Item')
                    ->counts('details'),
                TextColumn::make('user.name')
                    ->label('Diinput oleh')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('gudang_asal_id')
                    ->label('Gudang Asal')
                    ->relationship('gudangAsal', 'nama_gudang'),
                SelectFilter::make('gudang_tujuan_id')
                    ->label('Gudang Tujuan')
                    ->relationship('gudangTujuan', 'nama_gudang'),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('tanggal_dari'),
                        DatePicker::make('tanggal_sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_dari'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_sampai'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function ($record, DeleteAction $action) {
                        $stok = app(StokService::class);
                        try {
                            return DB::transaction(function () use ($stok, $record) {
                                $stok->balikkanPerpindahan($stok->snapshotPerpindahan($record));
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
                // No bulk delete
            ]);
    }
}
