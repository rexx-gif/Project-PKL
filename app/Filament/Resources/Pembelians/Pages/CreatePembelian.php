<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use App\Models\KartuStok;
use App\Models\MutasiStok;
use Filament\Resources\Pages\CreateRecord;

class CreatePembelian extends CreateRecord
{
    protected static string $resource = PembelianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hitung total dari detail items
        $details = $this->data['details'] ?? [];
        $total = 0;
        foreach ($details as $detail) {
            $total += (int) ($detail['subtotal'] ?? 0);
        }
        $data['total'] = $total;
        $data['neto'] = $total - (int) ($data['diskon'] ?? 0);

        return $data;
    }

    protected function afterCreate(): void
    {
        $pembelian = $this->record;

        // Untuk setiap detail beli, buat kartu stok dan update mutasi stok
        foreach ($pembelian->details as $detail) {
            // === KARTU STOK ===
            // Ambil saldo terakhir untuk barang & gudang ini
            $lastKartu = KartuStok::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $detail->gudang_id)
                ->latest('id')
                ->first();

            $saldoSebelumnya = $lastKartu ? (int) $lastKartu->saldo : 0;
            $saldoBaru = $saldoSebelumnya + (int) $detail->jumlah;

            KartuStok::create([
                'barang_id' => $detail->barang_id,
                'gudang_id' => $detail->gudang_id,
                'nomer_entry' => $pembelian->nomer_entry,
                'tanggal' => $pembelian->tanggal,
                'keterangan' => 'Pembelian dari ' . ($pembelian->supplier->nama_supplier ?? '-'),
                'jenis_transaksi' => 'masuk',
                'jumlah' => $detail->jumlah,
                'harga' => $detail->harga,
                'saldo' => $saldoBaru,
            ]);

            // === MUTASI STOK ===
            // Cari mutasi hari ini untuk barang & gudang ini
            $mutasi = MutasiStok::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $detail->gudang_id)
                ->where('tanggal', $pembelian->tanggal)
                ->first();

            if ($mutasi) {
                // Update mutasi yang sudah ada hari ini
                $mutasi->update([
                    'masuk' => $mutasi->masuk + (int) $detail->jumlah,
                    'saldo' => $mutasi->awal + ($mutasi->masuk + (int) $detail->jumlah) - $mutasi->keluar,
                ]);
            } else {
                // Cari saldo terakhir dari mutasi sebelumnya
                $lastMutasi = MutasiStok::where('barang_id', $detail->barang_id)
                    ->where('gudang_id', $detail->gudang_id)
                    ->latest('tanggal')
                    ->first();

                $awal = $lastMutasi ? (int) $lastMutasi->saldo : 0;

                MutasiStok::create([
                    'barang_id' => $detail->barang_id,
                    'gudang_id' => $detail->gudang_id,
                    'awal' => $awal,
                    'masuk' => $detail->jumlah,
                    'keluar' => 0,
                    'saldo' => $awal + (int) $detail->jumlah,
                    'tanggal' => $pembelian->tanggal,
                ]);
            }
        }
    }
}
