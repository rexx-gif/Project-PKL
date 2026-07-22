@php
    use App\Models\Barang;
    use Illuminate\Database\Eloquent\Builder;

    // JANGAN jalankan jika user belum login / di halaman login
    if (! auth()->check()) {
        return;
    }

    // Query barang stok menipis
    $lowStockItems = Barang::query()
        ->with('gudangs', 'jenisBarang')
        ->whereHas('gudangs', function (Builder $q) {
            $q->where('barang_gudang.stok', '<=', 5);
        })
        ->get();

    $jsonData = [];
    foreach ($lowStockItems as $b) {
        $gudangs = [];
        foreach ($b->gudangs as $g) {
            $gudangs[] = [
                'nama' => $g->nama_gudang,
                'stok' => (int) $g->pivot->stok,
            ];
        }
        $jsonData[] = [
            'nama' => $b->nama_barang,
            'jenis' => $b->jenisBarang->nama_jenis ?? '-',
            'harga' => 'Rp ' . number_format($b->harga_jual, 0, ',', '.'),
            'gudangs' => $gudangs,
        ];
    }

    // Cek halaman dashboard & status pernah tampil di sesi ini
    $isDashboard = request()->routeIs('filament.admin.pages.dashboard');
    $alreadyShown = session()->get('low_stock_swal_shown', false);
    $shouldAutoShow = $isDashboard && ! $alreadyShown && count($jsonData) > 0;

    if ($shouldAutoShow) {
        session()->put('low_stock_swal_shown', true);
    }
@endphp

@if(count($jsonData) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    var lowStockData = {!! json_encode($jsonData) !!};

    function buildLowStockHtml(items) {
        var rows = '';
        items.forEach(function (item) {
            var gudangBadges = '';
            item.gudangs.forEach(function (g) {
                var cls = g.stok <= 5
                    ? 'background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b'
                    : 'background:#27272a;color:#a1a1aa;border:1px solid #3f3f46';
                gudangBadges += '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;margin:2px;' + cls + '">'
                    + g.nama + ': ' + g.stok + ' pcs</span>';
            });
            rows += '<tr style="border-bottom:1px solid #27272a">'
                + '<td style="padding:10px 8px;font-weight:600;color:#fafafa;text-align:left">' + item.nama + '</td>'
                + '<td style="padding:10px 8px;text-align:left;white-space:nowrap"><span style="display:inline-block;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:500;background:#27272a;color:#a1a1aa;white-space:nowrap">' + item.jenis + '</span></td>'
                + '<td style="padding:10px 8px;text-align:left">' + gudangBadges + '</td>'
                + '<td style="padding:10px 8px;font-weight:700;color:#fafafa;text-align:right;white-space:nowrap">' + item.harga + '</td>'
                + '</tr>';
        });

        return '<div style="max-height:50vh;overflow-y:auto;margin-top:12px;border-radius:12px;border:1px solid #27272a">'
            + '<table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left">'
            + '<thead><tr style="background:#18181b;border-bottom:2px solid #3f3f46;position:sticky;top:0;z-index:1">'
            + '<th style="padding:10px 8px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.08em;background:#18181b">Barang</th>'
            + '<th style="padding:10px 8px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.08em;white-space:nowrap;background:#18181b">Kategori</th>'
            + '<th style="padding:10px 8px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.08em;background:#18181b">Gudang &amp; Sisa Stok</th>'
            + '<th style="padding:10px 8px;font-weight:700;color:#71717a;text-transform:uppercase;font-size:10px;letter-spacing:0.08em;text-align:right;background:#18181b">Harga Jual</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';
    }

    window.__showLowStockAlert = function () {
        Swal.fire({
            icon: 'warning',
            title: '<span style="color:#fafafa">Stok Menipis!</span>',
            html: '<p style="color:#a1a1aa;font-size:14px;margin-bottom:4px">Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> dengan stok &le; 5 pcs yang perlu segera di-restok.</p>'
                + buildLowStockHtml(lowStockData),
            width: '720px',
            showCloseButton: true,
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#ea580c',
            background: '#09090b',
            color: '#fafafa',
            customClass: {
                popup: 'swal-low-stock',
                closeButton: 'swal-close-dark',
            },
        });
    };

    @if($shouldAutoShow)
        window.__showLowStockAlert();
    @endif
});
</script>
@endif
