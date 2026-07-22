<?php

namespace App\Filament\Widgets;

use App\Models\Pembelian;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PembelianChartWidget extends ChartWidget
{
    protected ?string $heading = 'Grafik Modal Pembelian (7 Hari Terakhir)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $dates = collect();
        $totals = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dates->push($date->format('d M'));
            $sum = Pembelian::whereDate('tanggal', $date)->sum('neto');
            $totals->push($sum);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Pembelian (Rp)',
                    'data' => $totals->toArray(),
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $dates->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
