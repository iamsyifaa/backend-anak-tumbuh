<?php

namespace App\Services\DailyPeriod;

use Carbon\Carbon;

class DailyPeriodService
{
    public function __construct() {}

    /**
     * Mengembalikan tanggal periode aktif hari ini (server-side, bukan dari input client).
     * Ini sumber kebenaran tunggal untuk "hari ini itu tanggal berapa" di seluruh sistem.
     */
    public function getCurrentPeriod(): Carbon
    {
        return Carbon::now()->startOfDay();
    }

    /**
     * Cek apakah suatu tanggal masih boleh diisi (submit) sekarang.
     * Aturan: hanya periode HARI INI yang boleh diisi. Tidak ada backfill
     * untuk hari yang sudah lewat, dan tidak ada isi lebih awal untuk hari depan.
     */
    public function isPeriodOpenForSubmission(Carbon $date): bool
    {
        return $date->isSameDay($this->getCurrentPeriod());
    }

    /**
     * Cek eksplisit: apakah ini percobaan backfill (mengisi untuk tanggal yang sudah lewat)?
     * Dipakai nanti oleh SubmissionService (BE-007) untuk menolak permintaan semacam ini.
     */
    public function isBackfillAttempt(Carbon $date): bool
    {
        return $date->lt($this->getCurrentPeriod());
    }
}