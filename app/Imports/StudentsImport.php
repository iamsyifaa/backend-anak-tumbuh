<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

/**
 * Sengaja pakai ToCollection (bukan ToModel) supaya kita PUNYA KONTROL PENUH
 * atas kapan data disimpan ke database. ToModel akan langsung insert per baris
 * saat file dibaca — itu melanggar requirement "tidak ada data terbentuk
 * sebagian sebelum preview/commit disetujui".
 *
 * Kolom yang diharapkan di file (baris pertama = header):
 * full_name | nisn | birth_date | method | rombel_id
 *
 * birth_date format: YYYY-MM-DD
 * method: digital atau manual (tidak case sensitive, dinormalisasi di service)
 */
class StudentsImport implements ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
