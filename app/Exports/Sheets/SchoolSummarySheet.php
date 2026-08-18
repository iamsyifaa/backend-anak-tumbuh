<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SchoolSummarySheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private array $summary) {}

    public function array(): array
    {
        return [
            ['Rata-rata Poin per Siswa', $this->summary['average_points']],
            ['Tingkat Partisipasi Hari Ini (%)', $this->summary['today_participation_rate']],
        ];
    }

    public function headings(): array
    {
        return ['Metrik', 'Nilai'];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}
