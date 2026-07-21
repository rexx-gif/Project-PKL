<?php

namespace App\Filament\Resources\PerpindahanBarangs\Pages;

use App\Exceptions\StokTidakCukupException;
use App\Filament\Resources\PerpindahanBarangs\PerpindahanBarangResource;
use App\Services\StokService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPerpindahanBarang extends EditRecord
{
    protected static string $resource = PerpindahanBarangResource::class;

    private array $snapshotLama = [];

    protected function beforeSave(): void
    {
        $stok = app(StokService::class);
        $record = $this->record->fresh();
        $this->snapshotLama = $stok->snapshotPerpindahan($record);

        // delta = -lama +baru per (barang, gudang); cek tidak bikin minus
        $deltas = [];
        
        // Balikkan efek lama: +jumlah di asal lama, -jumlah di tujuan lama
        foreach ($this->snapshotLama['details'] as $d) {
            $asal = "{$d['barang_id']}:{$this->snapshotLama['gudang_asal_id']}";
            $tujuan = "{$d['barang_id']}:{$this->snapshotLama['gudang_tujuan_id']}";
            $deltas[$asal] = ($deltas[$asal] ?? 0) + $d['jumlah'];
            $deltas[$tujuan] = ($deltas[$tujuan] ?? 0) - $d['jumlah'];
        }

        // Terapkan efek baru: -jumlah di asal baru, +jumlah di tujuan baru
        $gudangAsalBaru = (int) $this->data['gudang_asal_id'];
        $gudangTujuanBaru = (int) $this->data['gudang_tujuan_id'];
        foreach ($this->data['details'] ?? [] as $d) {
            if (empty($d['barang_id'])) {
                continue;
            }
            $asal = "{$d['barang_id']}:{$gudangAsalBaru}";
            $tujuan = "{$d['barang_id']}:{$gudangTujuanBaru}";
            $deltas[$asal] = ($deltas[$asal] ?? 0) - (int) $d['jumlah'];
            $deltas[$tujuan] = ($deltas[$tujuan] ?? 0) + (int) $d['jumlah'];
        }

        try {
            $stok->validasiDelta($deltas);
        } catch (StokTidakCukupException $e) {
            Notification::make()->danger()
                ->title('Perubahan ditolak')
                ->body($e->getMessage())
                ->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        $stok = app(StokService::class);
        DB::transaction(function () use ($stok) {
            // balikkan efek lama dengan validasi dinonaktifkan per-langkah (sudah divalidasi via delta),
            // lalu terapkan efek baru
            foreach ($this->snapshotLama['details'] as $detail) {
                $nomerEntry = $this->snapshotLama['nomer_entry'] ?? 'PIN-' . $this->snapshotLama['id'];
                $konteks = [
                    'nomer_entry' => $nomerEntry,
                    'jenis' => \App\Models\KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan (edit) perpindahan ' . $nomerEntry,
                ];
                $stok->kurangiStok($detail['barang_id'], $this->snapshotLama['gudang_tujuan_id'], $detail['jumlah'], $konteks, validasi: false);
                $stok->tambahStok($detail['barang_id'], $this->snapshotLama['gudang_asal_id'], $detail['jumlah'], $konteks);
            }
            $stok->terapkanPerpindahan($this->record->fresh());
        });
    }

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
