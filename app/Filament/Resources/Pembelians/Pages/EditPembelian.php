<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPembelian extends EditRecord
{
    protected static string $resource = PembelianResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate total from detail items
        $details = $this->data['details'] ?? [];
        $total = 0;
        foreach ($details as $detail) {
            $total += (int) ($detail['subtotal'] ?? 0);
        }
        $data['total'] = $total;
        $data['neto'] = $total - (int) ($data['diskon'] ?? 0);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
