<?php

namespace App\Services\DailyPeriod;

use Carbon\Carbon;

class DailyPeriodService
{
    public function __construct() {}

    /**
     * Mengembalikan tanggal periode aktif hari ini (server-side, bukan dari input client).
     * Ini sumber kebenaran tunggal untuk "hari ini itu tanggal berapa" di seluruh sistem.
     *
     * [Update Gap Timezone, Requirement Bagian 8] $schoolTimezone opsional,
     * default null = tetap pakai timezone server (perilaku lama tidak berubah
     * kalau tidak diisi). Isi dengan timezone sekolah siswa untuk batas "hari
     * ini" yang benar sesuai zona waktu sekolah.
     */
    public function getCurrentPeriod(?string $schoolTimezone = null): Carbon
    {
        return Carbon::now($schoolTimezone)->startOfDay();
    }

    /**
     * Cek apakah suatu tanggal masih boleh diisi (submit) sekarang.
     * Aturan: hanya periode HARI INI yang boleh diisi. Tidak ada backfill
     * untuk hari yang sudah lewat, dan tidak ada isi lebih awal untuk hari depan.
     */
    public function isPeriodOpenForSubmission(Carbon $date, ?string $schoolTimezone = null): bool
    {
        return $date->isSameDay($this->getCurrentPeriod($schoolTimezone));
    }

    /**
     * Cek eksplisit: apakah ini percobaan backfill (mengisi untuk tanggal yang sudah lewat)?
     * Dipakai nanti oleh SubmissionService (BE-007) untuk menolak permintaan semacam ini.
     */
    public function isBackfillAttempt(Carbon $date, ?string $schoolTimezone = null): bool
    {
        return $date->lt($this->getCurrentPeriod($schoolTimezone));
    }
}