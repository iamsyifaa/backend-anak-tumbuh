<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SchoolTrendSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private array $trend) {}

    public function array(): array
    {
        return array_map(fn ($row) => [$row['date'], $row['points']], $this->trend);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Total Poin'];
    }

    public function title(): string
    {
        return 'Tren Harian';
    }
}
