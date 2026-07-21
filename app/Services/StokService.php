<?php

namespace App\Services;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Pembelian;
use App\Models\PerpindahanBarang;
use Illuminate\Support\Facades\DB;

class StokService
{
    public function tambahStok(int $barangId, int $gudangId, int $jumlah, array $konteks = []): void
    {
        $baris = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        if ($baris) {
            $saldoBaru = $baris->stok + $jumlah;
            DB::table('barang_gudang')->where('id', $baris->id)
                ->update(['stok' => $saldoBaru, 'updated_at' => now()]);
        } else {
            $saldoBaru = $jumlah;
            DB::table('barang_gudang')->insert([
                'barang_id' => $barangId,
                'gudang_id' => $gudangId,
                'stok' => $saldoBaru,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->catatKartu($barangId, $gudangId, $jumlah, $saldoBaru,
            $konteks['jenis'] ?? KartuStok::JENIS_MASUK, $konteks);
    }

    public function kurangiStok(int $barangId, int $gudangId, int $jumlah, array $konteks = [], bool $validasi = true): void
    {
        $baris = DB::table('barang_gudang')
            ->where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        $stokSekarang = $baris?->stok ?? 0;

        if ($validasi && $stokSekarang < $jumlah) {
            $barang = Barang::find($barangId);
            $gudang = Gudang::find($gudangId);
            throw new StokTidakCukupException(
                "Stok {$barang->nama_barang} di {$gudang->nama_gudang} tidak cukup " .
                "(tersedia: {$stokSekarang}, dibutuhkan: {$jumlah})."
            );
        }

        $saldoBaru = $stokSekarang - $jumlah;
        if ($baris) {
            DB::table('barang_gudang')->where('id', $baris->id)
                ->update(['stok' => $saldoBaru, 'updated_at' => now()]);
        } else {
            DB::table('barang_gudang')->insert([
                'barang_id' => $barangId, 'gudang_id' => $gudangId,
                'stok' => $saldoBaru, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->catatKartu($barangId, $gudangId, -$jumlah, $saldoBaru,
            $konteks['jenis'] ?? KartuStok::JENIS_KELUAR, $konteks);
    }

    public function validasiDelta(array $deltas): void
    {
        foreach ($deltas as $kunci => $delta) {
            if ($delta >= 0) {
                continue;
            }
            [$barangId, $gudangId] = array_map('intval', explode(':', $kunci));
            $stok = (int) DB::table('barang_gudang')
                ->where('barang_id', $barangId)
                ->where('gudang_id', $gudangId)
                ->value('stok');
            if ($stok + $delta < 0) {
                $barang = Barang::find($barangId);
                $gudang = Gudang::find($gudangId);
                throw new StokTidakCukupException(
                    "Perubahan ini akan membuat stok {$barang->nama_barang} di " .
                    "{$gudang->nama_gudang} jadi minus (tersedia: {$stok}, perubahan: {$delta})."
                );
            }
        }
    }

    private function catatKartu(int $barangId, int $gudangId, int $jumlah, int $saldo, string $jenis, array $konteks): void
    {
        KartuStok::create([
            'barang_id' => $barangId,
            'gudang_id' => $gudangId,
            'nomer_entry' => $konteks['nomer_entry'] ?? null,
            'tanggal' => $konteks['tanggal'] ?? now(),
            'keterangan' => $konteks['keterangan'] ?? null,
            'jenis_transaksi' => $jenis,
            'jumlah' => $jumlah,
            'harga' => $konteks['harga'] ?? 0,
            'saldo' => $saldo,
        ]);
    }

    public function terapkanPembelian(Pembelian $pembelian): void
    {
        DB::transaction(function () use ($pembelian) {
            $pembelian->loadMissing('details.barang', 'supplier');
            foreach ($pembelian->details as $detail) {
                $this->tambahStok($detail->barang_id, $pembelian->gudang_id, $detail->jumlah, [
                    'nomer_entry' => $pembelian->nomer_entry,
                    'tanggal' => $pembelian->tanggal,
                    'harga' => $detail->harga,
                    'keterangan' => 'Pembelian dari ' . ($pembelian->supplier->nama_supplier ?? '-'),
                ]);
                // harga master ikut harga beli terakhir
                $detail->barang->update(['harga_beli' => $detail->harga]);
            }
        });
    }

    public function snapshotPembelian(Pembelian $pembelian): array
    {
        return [
            'gudang_id' => $pembelian->gudang_id,
            'nomer_entry' => $pembelian->nomer_entry,
            'details' => $pembelian->details()->get(['barang_id', 'jumlah'])
                ->map(fn ($d) => ['barang_id' => $d->barang_id, 'jumlah' => $d->jumlah])
                ->all(),
        ];
    }

    public function balikkanPembelian(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot['details'] as $detail) {
                $this->kurangiStok($detail['barang_id'], $snapshot['gudang_id'], $detail['jumlah'], [
                    'nomer_entry' => $snapshot['nomer_entry'],
                    'jenis' => KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan pembelian ' . $snapshot['nomer_entry'],
                ]);
            }
        });
    }

    public function terapkanPerpindahan(PerpindahanBarang $pindah): void
    {
        DB::transaction(function () use ($pindah) {
            $pindah->loadMissing('details', 'gudangAsal', 'gudangTujuan');
            foreach ($pindah->details as $detail) {
                $konteks = [
                    'nomer_entry' => $pindah->nomer_entry ?? 'PIN-' . $pindah->id,
                    'tanggal' => $pindah->tanggal,
                    'keterangan' => "Pindah {$pindah->gudangAsal->nama_gudang} → {$pindah->gudangTujuan->nama_gudang}",
                ];
                $this->kurangiStok($detail->barang_id, $pindah->gudang_asal_id, $detail->jumlah,
                    $konteks + ['jenis' => KartuStok::JENIS_PINDAH_KELUAR]);
                $this->tambahStok($detail->barang_id, $pindah->gudang_tujuan_id, $detail->jumlah,
                    $konteks + ['jenis' => KartuStok::JENIS_PINDAH_MASUK]);
            }
        });
    }

    public function snapshotPerpindahan(PerpindahanBarang $pindah): array
    {
        return [
            'id' => $pindah->id,
            'nomer_entry' => $pindah->nomer_entry,
            'gudang_asal_id' => $pindah->gudang_asal_id,
            'gudang_tujuan_id' => $pindah->gudang_tujuan_id,
            'details' => $pindah->details()->get(['barang_id', 'jumlah'])
                ->map(fn ($d) => ['barang_id' => $d->barang_id, 'jumlah' => $d->jumlah])
                ->all(),
        ];
    }

    public function balikkanPerpindahan(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot['details'] as $detail) {
                $nomerEntry = $snapshot['nomer_entry'] ?? 'PIN-' . $snapshot['id'];
                $konteks = [
                    'nomer_entry' => $nomerEntry,
                    'jenis' => KartuStok::JENIS_KOREKSI,
                    'keterangan' => 'Pembalikan perpindahan ' . $nomerEntry,
                ];
                $this->kurangiStok($detail['barang_id'], $snapshot['gudang_tujuan_id'], $detail['jumlah'], $konteks);
                $this->tambahStok($detail['barang_id'], $snapshot['gudang_asal_id'], $detail['jumlah'], $konteks);
            }
        });
    }
}
