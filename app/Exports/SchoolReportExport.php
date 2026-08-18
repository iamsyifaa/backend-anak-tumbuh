<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SchoolReportExport implements WithMultipleSheets
{
    public function __construct(private array $data) {}

    public function sheets(): array
    {
        return [
            'Ringkasan' => new Sheets\SchoolSummarySheet($this->data['summary']),
            'Per Rombel' => new Sheets\RombelAchievementSheet($this->data['rombels']),
            'Tren Harian' => new Sheets\SchoolTrendSheet($this->data['trend']),
        ];
    }
}
