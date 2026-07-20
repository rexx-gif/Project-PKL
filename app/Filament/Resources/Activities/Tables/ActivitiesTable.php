<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Oleh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Aksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_id')
                    ->label('ID Objek')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('properties')
                    ->label('Perubahan')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }
                        $changes = [];
                        $old = $state['old'] ?? [];
                        $attributes = $state['attributes'] ?? [];
                        
                        foreach ($attributes as $key => $newValue) {
                            $oldValue = $old[$key] ?? null;
                            if ($oldValue !== $newValue) {
                                $changes[] = "$key: " . json_encode($oldValue) . " -> " . json_encode($newValue);
                            }
                        }
                        
                        return implode(', ', $changes);
                    })
                    ->limit(80)
                    ->tooltip(fn ($state) => json_encode($state, JSON_PRETTY_PRINT)),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }
}
