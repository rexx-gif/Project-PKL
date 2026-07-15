<?php

namespace App\Filament\Resources\MutasiStoks;

use App\Filament\Resources\MutasiStoks\Pages\ListMutasiStoks;
use App\Filament\Resources\MutasiStoks\Tables\MutasiStoksTable;
use App\Models\MutasiStok;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MutasiStokResource extends Resource
{
    protected static ?string $model = MutasiStok::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Mutasi Stok';

    protected static ?string $modelLabel = 'Mutasi Stok';

    protected static ?string $pluralModelLabel = 'Mutasi Stok';

    // Tidak ada form karena read-only
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return MutasiStoksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false; // Tidak bisa create manual, otomatis dari pembelian
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutasiStoks::route('/'),
        ];
    }
}
