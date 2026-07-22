<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PenjualanChartWidget extends ChartWidget
{
    protected ?string $heading = 'Grafik Omset Penjualan (7 Hari Terakhir)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $dates = collect();
        $totals = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dates->push($date->format('d M'));
            $sum = Penjualan::whereDate('tanggal', $date)->sum('neto');
            $totals->push($sum);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Omset Penjualan (Rp)',
                    'data' => $totals->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
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
