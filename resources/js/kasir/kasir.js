// =====================================================================
// LOGIC KASIR (POS)
// ---------------------------------------------------------------------
// File ini cuma ngurusin tampilan + keranjang. Semua data lewat api.js,
// jadi pas backend temenmu jadi tinggal ubah USE_MOCK di api.js.
// =====================================================================

import {
    getBarang,
    getJenisBarang,
    getGudang,
    getCustomers,
    simpanPenjualan,
    USE_MOCK,
} from './api.js';

// ------------------------- STATE -------------------------

const state = {
    barang: [],
    jenisBarang: [],
    gudang: [],
    customers: [],
    // keranjang: [{ barang_id, nama_barang, satuan, harga, jumlah, diskon }]
    cart: [],
    gudangId: null,
    customerId: null,
    filterJenis: null,
    search: '',
    diskonTransaksi: 0,
    jenisPembayaran: 'tunai',
    bayar: 0,
};

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// ------------------------- HITUNGAN -------------------------

function subtotalItem(item) {
    return item.harga * item.jumlah - item.diskon;
}

function totalKotor() {
    return state.cart.reduce((sum, item) => sum + subtotalItem(item), 0);
}

function totalNeto() {
    return Math.max(0, totalKotor() - state.diskonTransaksi);
}

function kembalian() {
    return Math.max(0, state.bayar - totalNeto());
}

function stokBarang(barang) {
    if (!state.gudangId) return 0;
    return barang.stok?.[state.gudangId] ?? 0;
}

// ------------------------- KERANJANG -------------------------

function tambahKeCart(barangId) {
    const barang = state.barang.find((b) => b.id === barangId);
    if (!barang) return;

    const stok = stokBarang(barang);
    const existing = state.cart.find((i) => i.barang_id === barangId);
    const jumlahSekarang = existing ? existing.jumlah : 0;

    if (jumlahSekarang + 1 > stok) {
        toast(`Stok ${barang.nama_barang} di gudang ini tinggal ${stok}`, true);
        return;
    }

    if (existing) {
        existing.jumlah += 1;
    } else {
        state.cart.push({
            barang_id: barang.id,
            nama_barang: barang.nama_barang,
            satuan: barang.satuan ?? 'pcs',
            harga: barang.harga_jual,
            jumlah: 1,
            diskon: 0,
        });
    }
    render();
}

function ubahJumlah(barangId, delta) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    const stok = barang ? stokBarang(barang) : Infinity;

    const baru = item.jumlah + delta;
    if (baru > stok) {
        toast(`Stok maksimal ${stok}`, true);
        return;
    }
    if (baru <= 0) {
        state.cart = state.cart.filter((i) => i.barang_id !== barangId);
    } else {
        item.jumlah = baru;
    }
    render();
}

function hapusItem(barangId) {
    state.cart = state.cart.filter((i) => i.barang_id !== barangId);
    render();
}

function resetTransaksi() {
    state.cart = [];
    state.diskonTransaksi = 0;
    state.bayar = 0;
    state.jenisPembayaran = 'tunai';
    render();
}

// ------------------------- BAYAR -------------------------

async function prosesBayar() {
    if (state.cart.length === 0) {
        toast('Keranjang masih kosong', true);
        return;
    }
    if (!state.gudangId) {
        toast('Pilih gudang dulu', true);
        return;
    }
    if (state.jenisPembayaran === 'tunai' && state.bayar < totalNeto()) {
        toast('Uang bayar kurang dari total', true);
        return;
    }

    const payload = {
        // nomer nota dibikin server (KasirController@simpan) biar urut & gak bentrok
        customer_id: state.customerId,
        gudang_id: state.gudangId,
        tanggal: new Date().toISOString().slice(0, 10),
        total: totalKotor(),
        diskon: state.diskonTransaksi,
        neto: totalNeto(),
        jenis_pembayaran: state.jenisPembayaran,
        bayar: state.jenisPembayaran === 'tunai' ? state.bayar : totalNeto(),
        kembalian: state.jenisPembayaran === 'tunai' ? kembalian() : 0,
        details: state.cart.map((i) => ({
            barang_id: i.barang_id,
            gudang_id: state.gudangId,
            satuan: i.satuan,
            jumlah: i.jumlah,
            harga: i.harga,
            diskon: i.diskon,
            subtotal: subtotalItem(i),
        })),
    };

    const btn = document.getElementById('btn-bayar');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    try {
        const saved = await simpanPenjualan(payload);
        tampilkanStruk({ ...payload, nomer_nota: saved.nomer_nota, kembalian: saved.kembalian ?? payload.kembalian });
        // kurangi stok di layar sesuai yang barusan kejual
        // (datanya disuntik sekali pas halaman kebuka, jadi gak bisa re-fetch)
        for (const d of payload.details) {
            const b = state.barang.find((x) => x.id === d.barang_id);
            if (b && b.stok[d.gudang_id] != null) b.stok[d.gudang_id] -= d.jumlah;
        }
        resetTransaksi();
    } catch (e) {
        toast(e.message ?? 'Gagal menyimpan transaksi', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Bayar';
    }
}

// ------------------------- STRUK -------------------------

function tampilkanStruk(payload) {
    const customer = state.customers.find((c) => c.id_customer === payload.customer_id);
    const gudang = state.gudang.find((g) => g.id === payload.gudang_id);

    const rows = payload.details
        .map((d) => {
            const nama = state.barang.find((b) => b.id === d.barang_id)?.nama_barang ?? '-';
            return `<tr>
                <td class="py-0.5 pr-2">${nama}</td>
                <td class="py-0.5 text-right whitespace-nowrap">${d.jumlah} x ${rupiah(d.harga)}</td>
                <td class="py-0.5 pl-2 text-right">${rupiah(d.subtotal)}</td>
            </tr>`;
        })
        .join('');

    document.getElementById('struk-body').innerHTML = `
        <div class="text-center mb-3">
            <p class="font-bold text-lg">TOKO PKL</p>
            <p class="text-xs text-gray-500">${gudang?.nama_gudang ?? ''}</p>
            <p class="text-xs text-gray-500">${payload.nomer_nota} &middot; ${payload.tanggal}</p>
            <p class="text-xs text-gray-500">Customer: ${customer?.nama_customer ?? 'Umum'}</p>
        </div>
        <table class="w-full text-sm border-y border-dashed border-gray-300 py-2 my-2">${rows}</table>
        <div class="text-sm space-y-1 mt-2">
            <div class="flex justify-between"><span>Total</span><span>${rupiah(payload.total)}</span></div>
            <div class="flex justify-between"><span>Diskon</span><span>- ${rupiah(payload.diskon)}</span></div>
            <div class="flex justify-between font-bold"><span>Neto</span><span>${rupiah(payload.neto)}</span></div>
            <div class="flex justify-between"><span>Bayar (${payload.jenis_pembayaran})</span><span>${rupiah(payload.bayar)}</span></div>
            <div class="flex justify-between"><span>Kembalian</span><span>${rupiah(payload.kembalian)}</span></div>
        </div>
        <p class="text-center text-xs text-gray-400 mt-4">-- Terima kasih --</p>
    `;
    document.getElementById('modal-struk').classList.remove('hidden');
}

// ------------------------- RENDER -------------------------

function renderProduk() {
    const grid = document.getElementById('grid-produk');
    const q = state.search.toLowerCase();

    const list = state.barang.filter((b) => {
        if (state.filterJenis && b.jenis_barang_id !== state.filterJenis) return false;
        if (q && !b.nama_barang.toLowerCase().includes(q)) return false;
        return true;
    });

    if (list.length === 0) {
        grid.innerHTML = `<p class="col-span-full text-center text-gray-400 py-10">Barang tidak ditemukan</p>`;
        return;
    }

    grid.innerHTML = list
        .map((b) => {
            const stok = stokBarang(b);
            const habis = stok <= 0;
            return `<button data-add="${b.id}" ${habis ? 'disabled' : ''}
                class="text-left bg-white rounded-xl border border-gray-200 p-3 hover:border-blue-400 hover:shadow transition
                       ${habis ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}">
                <p class="font-medium text-sm leading-tight">${b.nama_barang}</p>
                <p class="text-blue-600 font-semibold mt-1">${rupiah(b.harga_jual)}</p>
                <p class="text-xs mt-1 ${stok <= 5 ? 'text-red-500' : 'text-gray-400'}">Stok: ${stok} ${b.satuan ?? ''}</p>
            </button>`;
        })
        .join('');
}

function renderCart() {
    const wrap = document.getElementById('cart-items');

    if (state.cart.length === 0) {
        wrap.innerHTML = `<p class="text-center text-gray-400 text-sm py-8">Belum ada barang.<br>Klik produk di kiri untuk menambah.</p>`;
    } else {
        wrap.innerHTML = state.cart
            .map(
                (i) => `<div class="flex items-center gap-2 py-2 border-b border-gray-100">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">${i.nama_barang}</p>
                    <p class="text-xs text-gray-500">${rupiah(i.harga)} / ${i.satuan}</p>
                </div>
                <div class="flex items-center gap-1">
                    <button data-minus="${i.barang_id}" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold">−</button>
                    <span class="w-8 text-center text-sm">${i.jumlah}</span>
                    <button data-plus="${i.barang_id}" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold">+</button>
                </div>
                <p class="w-24 text-right text-sm font-semibold">${rupiah(subtotalItem(i))}</p>
                <button data-del="${i.barang_id}" class="text-red-400 hover:text-red-600 px-1" title="Hapus">✕</button>
            </div>`
            )
            .join('');
    }

    document.getElementById('lbl-total').textContent = rupiah(totalKotor());
    document.getElementById('lbl-neto').textContent = rupiah(totalNeto());
    document.getElementById('lbl-kembalian').textContent = rupiah(kembalian());

    const inputDiskon = document.getElementById('input-diskon');
    if (document.activeElement !== inputDiskon) inputDiskon.value = state.diskonTransaksi || '';
    const inputBayar = document.getElementById('input-bayar');
    if (document.activeElement !== inputBayar) inputBayar.value = state.bayar || '';

    // uang pas & tombol bayar
    document.getElementById('btn-uang-pas').textContent = `Uang pas (${rupiah(totalNeto())})`;
    document.getElementById('row-tunai').style.display = state.jenisPembayaran === 'tunai' ? '' : 'none';
}

function render() {
    renderProduk();
    renderCart();
}

// ------------------------- TOAST -------------------------

let toastTimer;
function toast(msg, error = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50 transition
        ${error ? 'bg-red-500' : 'bg-gray-800'}`;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 2500);
}

// ------------------------- INIT -------------------------

async function init() {
    const [barang, jenis, gudang, customers] = await Promise.all([
        getBarang(),
        getJenisBarang(),
        getGudang(),
        getCustomers(),
    ]);
    state.barang = barang;
    state.jenisBarang = jenis;
    state.gudang = gudang;
    state.customers = customers;
    state.gudangId = gudang[0]?.id ?? null;

    // dropdown gudang
    const selGudang = document.getElementById('select-gudang');
    selGudang.innerHTML = gudang.map((g) => `<option value="${g.id}">${g.nama_gudang}</option>`).join('');
    selGudang.addEventListener('change', (e) => {
        state.gudangId = Number(e.target.value);
        state.cart = []; // stok beda per gudang, jadi keranjang direset
        render();
    });

    // dropdown customer
    const selCustomer = document.getElementById('select-customer');
    selCustomer.innerHTML =
        `<option value="">Umum</option>` +
        customers.map((c) => `<option value="${c.id_customer}">${c.nama_customer}</option>`).join('');
    selCustomer.addEventListener('change', (e) => {
        state.customerId = e.target.value ? Number(e.target.value) : null;
    });

    // filter kategori
    const wrapFilter = document.getElementById('filter-jenis');
    wrapFilter.innerHTML =
        `<button data-jenis="" class="chip-jenis px-3 py-1.5 rounded-full text-sm bg-blue-600 text-white">Semua</button>` +
        jenis
            .map(
                (j) =>
                    `<button data-jenis="${j.id}" class="chip-jenis px-3 py-1.5 rounded-full text-sm bg-white border border-gray-200 hover:border-blue-400">${j.nama_jenis}</button>`
            )
            .join('');
    wrapFilter.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-jenis]');
        if (!btn) return;
        state.filterJenis = btn.dataset.jenis ? Number(btn.dataset.jenis) : null;
        wrapFilter.querySelectorAll('.chip-jenis').forEach((b) => {
            b.className = `chip-jenis px-3 py-1.5 rounded-full text-sm ${b === btn ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 hover:border-blue-400'
                }`;
        });
        renderProduk();
    });

    // search
    document.getElementById('input-search').addEventListener('input', (e) => {
        state.search = e.target.value;
        renderProduk();
    });

    // klik produk / tombol keranjang (event delegation)
    document.getElementById('grid-produk').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-add]');
        if (btn) tambahKeCart(Number(btn.dataset.add));
    });
    document.getElementById('cart-items').addEventListener('click', (e) => {
        const plus = e.target.closest('[data-plus]');
        const minus = e.target.closest('[data-minus]');
        const del = e.target.closest('[data-del]');
        if (plus) ubahJumlah(Number(plus.dataset.plus), 1);
        if (minus) ubahJumlah(Number(minus.dataset.minus), -1);
        if (del) hapusItem(Number(del.dataset.del));
    });

    // diskon & bayar
    document.getElementById('input-diskon').addEventListener('input', (e) => {
        state.diskonTransaksi = Number(e.target.value) || 0;
        renderCart();
    });
    document.getElementById('input-bayar').addEventListener('input', (e) => {
        state.bayar = Number(e.target.value) || 0;
        renderCart();
    });
    document.getElementById('btn-uang-pas').addEventListener('click', () => {
        state.bayar = totalNeto();
        renderCart();
    });

    // jenis pembayaran
    document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.jenisPembayaran = e.target.value;
            renderCart();
        });
    });

    // aksi
    document.getElementById('btn-bayar').addEventListener('click', prosesBayar);
    document.getElementById('btn-reset').addEventListener('click', resetTransaksi);
    document.getElementById('btn-tutup-struk').addEventListener('click', () => {
        document.getElementById('modal-struk').classList.add('hidden');
    });
    document.getElementById('btn-print-struk').addEventListener('click', () => window.print());

    if (USE_MOCK) {
        document.getElementById('badge-mock').classList.remove('hidden');
    }

    document.getElementById('loading').classList.add('hidden');
    document.getElementById('kasir-app').classList.remove('hidden');
    render();
}

init().catch((e) => {
    document.getElementById('loading').textContent = 'Gagal memuat data: ' + e.message;
});
