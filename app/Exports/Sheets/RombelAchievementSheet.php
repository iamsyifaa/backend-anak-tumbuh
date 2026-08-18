<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RombelAchievementSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Collection $rombels) {}

    public function collection(): Collection
    {
        return $this->rombels;
    }

    public function map($row): array
    {
        return [
            $row['rombel_name'],
            $row['student_count'],
            $row['average_points'],
        ];
    }

    public function headings(): array
    {
        return ['Rombel', 'Jumlah Siswa', 'Rata-rata Poin'];
    }

    public function title(): string
    {
        return 'Per Rombel';
    }
}
