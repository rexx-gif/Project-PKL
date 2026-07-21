<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Customer;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\Penjualan;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    /**
     * Halaman kasir. Data langsung disuntik ke Blade (tanpa API),
     * nanti dibaca JavaScript lewat window.KASIR_DATA.
     */
    public function index()
    {
        return view('kasir', [
            'kasirData' => [
                'barang' => Barang::with('gudangs')->get()->map(fn ($b) => [
                    'id' => $b->id,
                    'jenis_barang_id' => $b->jenis_barang_id,
                    'nama_barang' => $b->nama_barang,
                    'harga_jual' => (int) $b->harga_jual,
                    'satuan' => $b->satuan ?? 'pcs',
                    // stok per gudang dari pivot barang_gudang: { gudang_id: jumlah }
                    'stok' => $b->gudangs->mapWithKeys(fn ($g) => [$g->id => (int) $g->pivot->stok]),
                ]),
                'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
                'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
                'customers' => Customer::all(['id_customer', 'nama_customer', 'no_telp']),
            ],
        ]);
    }

    /**
     * Simpan transaksi kasir.
     * Alurnya ngikutin pola CreatePembelian::afterCreate() punya admin Filament,
     * tapi arah stoknya keluar via StokService::kurangiStok().
     */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id_customer'],
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'diskon' => ['required', 'integer', 'min:0'],
            'jenis_pembayaran' => ['required', 'in:tunai,qris,transfer'],
            'bayar' => ['required', 'integer', 'min:0'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'details.*.jumlah' => ['required', 'integer', 'min:1'],
            'details.*.diskon' => ['required', 'integer', 'min:0'],
            'details.*.satuan' => ['nullable', 'string'],
        ]);

        $penjualan = DB::transaction(function () use ($data) {
            $gudangId = $data['gudang_id'];

            // total & harga dihitung ulang di server (jangan percaya angka dari browser)
            $total = 0;
            $details = [];
            foreach ($data['details'] as $d) {
                // lockForUpdate biar aman kalau dua kasir jualan barang sama barengan
                $barang = Barang::lockForUpdate()->findOrFail($d['barang_id']);

                $pivot = $barang->gudangs()->where('gudang.id', $gudangId)->first();
                $stok = $pivot ? (int) $pivot->pivot->stok : 0;
                if ($stok < $d['jumlah']) {
                    abort(422, "Stok {$barang->nama_barang} tinggal {$stok}");
                }

                $subtotal = ((int) $barang->harga_jual * $d['jumlah']) - $d['diskon'];
                $total += $subtotal;
                $details[] = [
                    'barang' => $barang,
                    'jumlah' => $d['jumlah'],
                    'harga' => (int) $barang->harga_jual,
                    'diskon' => $d['diskon'],
                    'subtotal' => $subtotal,
                    'satuan' => $d['satuan'] ?? 'pcs',
                ];
            }

            $neto = max(0, $total - $data['diskon']);
            if ($data['jenis_pembayaran'] === 'tunai' && $data['bayar'] < $neto) {
                abort(422, 'Uang bayar kurang dari total');
            }
            $bayar = $data['jenis_pembayaran'] === 'tunai' ? $data['bayar'] : $neto;

            // nomer nota urut dibikin server biar gak bentrok kalau kasirnya lebih dari satu.
            // Ambil tanggal (Y-m-d) saja dari $data['tanggal'] yang mungkin berisi jam
            $tanggalOnly = substr($data['tanggal'], 0, 10);
            $urutan = Penjualan::whereDate('tanggal', $tanggalOnly)
                ->lockForUpdate()->get('id')->count() + 1;
            $nomerNota = 'PJ-' . str_replace('-', '', $tanggalOnly) . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);

            $penjualan = Penjualan::create([
                'nomer_nota' => $nomerNota,
                'customer_id' => $data['customer_id'],
                'gudang_id' => $gudangId,
                'tanggal' => $data['tanggal'],
                'total' => $total,
                'diskon' => $data['diskon'],
                'neto' => $neto,
                'jenis_pembayaran' => $data['jenis_pembayaran'],
                'bayar' => $bayar,
                'kembalian' => max(0, $bayar - $neto),
            ]);

            foreach ($details as $d) {
                DetailJual::create([
                    'penjualan_id' => $penjualan->id,
                    'barang_id' => $d['barang']->id,
                    'gudang_id' => $gudangId,
                    'satuan' => $d['satuan'],
                    'jumlah' => $d['jumlah'],
                    'harga' => $d['harga'],
                    'diskon' => $d['diskon'],
                    'subtotal' => $d['subtotal'],
                ]);

                // === STOK & KARTU STOK via StokService (sama kayak pembelian di admin) ===
                app(StokService::class)->kurangiStok(
                    barangId: $d['barang']->id,
                    gudangId: $gudangId,
                    jumlah: $d['jumlah'],
                    konteks: [
                        'nomer_entry' => $nomerNota,
                        'tanggal' => $data['tanggal'],
                        'harga' => $d['harga'],
                        'keterangan' => 'Penjualan kasir',
                        'jenis' => KartuStok::JENIS_KELUAR,
                    ],
                    validasi: false, // sudah divalidasi di atas
                );
            }

            return $penjualan;
        });

        return response()->json($penjualan->load('details'));
    }
}
