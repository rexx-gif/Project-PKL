<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - Toko PKL</title>
    {{-- data dari database disuntik ke JS di sini (tanpa API). --}}
    {{-- $kasirData dikirim KasirController@index; @isset biar view tetep bisa --}}
    {{-- dibuka manual tanpa controller (jatuh ke mode mock otomatis) --}}
    @isset($kasirData)
        <script>window.KASIR_DATA = @json($kasirData);</script>
    @endisset
    @vite(['resources/css/app.css', 'resources/js/kasir/kasir.js'])
    <style>
        /* pas print, cuma struk yang keluar */
        @media print {
            body * { visibility: hidden; }
            #modal-struk, #modal-struk * { visibility: visible; }
            #modal-struk { position: absolute; inset: 0; background: white; }
            #struk-actions { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800">

    <p id="loading" class="text-center text-gray-400 py-20">Memuat data...</p>

    <div id="kasir-app" class="hidden h-screen flex flex-col">

        {{-- HEADER --}}
        <header class="bg-white border-b border-gray-200 px-4 py-3 flex flex-wrap items-center gap-3">
            <h1 class="text-lg font-bold text-blue-600">🛒 Kasir</h1>
            <span id="badge-mock" class="hidden text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
                mode simulasi (data mock)
            </span>
            <div class="flex-1"></div>
            <label class="text-sm text-gray-500">Gudang:</label>
            <select id="select-gudang" class="text-sm border border-gray-200 rounded-lg px-2 py-1.5 bg-white"></select>
            <label class="text-sm text-gray-500">Customer:</label>
            <select id="select-customer" class="text-sm border border-gray-200 rounded-lg px-2 py-1.5 bg-white"></select>
        </header>

        <div class="flex-1 flex overflow-hidden">

            {{-- KIRI: DAFTAR PRODUK --}}
            <main class="flex-1 flex flex-col p-4 overflow-hidden">
                <input id="input-search" type="text" placeholder="Cari barang... "
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 mb-3 bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
                <div id="filter-jenis" class="flex flex-wrap gap-2 mb-3"></div>
                <div id="grid-produk"
                    class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 content-start pb-4"></div>
            </main>

            {{-- KANAN: KERANJANG --}}
            <aside class="w-[380px] bg-white border-l border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold">Keranjang</h2>
                    <button id="btn-reset" class="text-xs text-red-500 hover:underline">Kosongkan</button>
                </div>

                <div id="cart-items" class="flex-1 overflow-y-auto px-4"></div>

                <div class="border-t border-gray-100 p-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total</span>
                        <span id="lbl-total" class="font-medium">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Diskon (Rp)</span>
                        <input id="input-diskon" type="number" min="0" placeholder="0"
                            class="w-28 text-right border border-gray-200 rounded-lg px-2 py-1">
                    </div>
                    <div class="flex justify-between text-base font-bold">
                        <span>Neto</span>
                        <span id="lbl-neto" class="text-blue-600">Rp 0</span>
                    </div>

                    <div class="flex gap-2 pt-1">
                        @foreach (['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'] as $val => $label)
                            <label class="flex-1 text-center border border-gray-200 rounded-lg py-1.5 cursor-pointer
                                          has-checked:bg-blue-600 has-checked:text-white has-checked:border-blue-600">
                                <input type="radio" name="jenis_pembayaran" value="{{ $val }}"
                                    class="hidden" {{ $val === 'tunai' ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div id="row-tunai" class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Bayar (Rp)</span>
                            <input id="input-bayar" type="number" min="0" placeholder="0"
                                class="w-32 text-right border border-gray-200 rounded-lg px-2 py-1">
                        </div>
                        <button id="btn-uang-pas"
                            class="w-full text-xs bg-gray-100 hover:bg-gray-200 rounded-lg py-1.5">Uang pas</button>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kembalian</span>
                            <span id="lbl-kembalian" class="font-medium">Rp 0</span>
                        </div>
                    </div>

                    <button id="btn-bayar"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold rounded-xl py-3 mt-1">
                        Bayar
                    </button>
                </div>
            </aside>
        </div>
    </div>

    {{-- MODAL STRUK --}}
    <div id="modal-struk" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-40 p-4">
        <div class="bg-white rounded-2xl w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
            <div id="struk-body"></div>
            <div id="struk-actions" class="flex gap-2 mt-5">
                <button id="btn-print-struk"
                    class="flex-1 bg-gray-800 hover:bg-gray-900 text-white rounded-xl py-2.5 text-sm">🖨️ Print</button>
                <button id="btn-tutup-struk"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 rounded-xl py-2.5 text-sm">Transaksi Baru</button>
            </div>
        </div>
    </div>

    <div id="toast" class="hidden"></div>
</body>
</html>
