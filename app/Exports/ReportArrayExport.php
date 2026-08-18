<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportable generic — menerima array baris + judul kolom apa saja,
 * supaya 1 class ini bisa dipakai untuk laporan student/rombel/school/
 * achievement tanpa bikin class Export terpisah per jenis laporan.
 */
class ReportArrayExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
