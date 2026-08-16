<?php

namespace App\Services\Business;

use App\Models\ExpTransaction;
use Illuminate\Database\Eloquent\Collection;

class ExpService
{
    public function __construct() {}

    // INGAT: Poin dan EXP wajib dihitung terpisah — jangan digabung di satu class.

    /**
     * Catat satu transaksi EXP untuk user tertentu.
     *
     * Tabel exp_transactions bersifat insert-only (tidak ada updated_at)
     * agar histori transaksi tidak pernah berubah walau konfigurasi
     * berubah di kemudian hari (immutable history principle).
     *
     * @param  int         $userId
     * @param  int         $amount      Bisa positif (dapat EXP) atau negatif (koreksi).
     * @param  string      $sourceType  Contoh: 'submission_answer'.
     * @param  int         $sourceId    ID baris sumber, contoh: id submission_answers.
     * @param  string|\DateTimeInterface $periodDate  Tanggal periode harian saat EXP didapat.
     */
    public function record(
        int $userId,
        int $amount,
        string $sourceType,
        int $sourceId,
        string|\DateTimeInterface $periodDate
    ): ExpTransaction {
        return ExpTransaction::create([
            'user_id' => $userId,
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'period_date' => $periodDate,
        ]);
    }

    /**
     * Total EXP milik user (SUM semua transaksi). Dipakai untuk hitung
     * Level. Level pakai EXP, ranking pakai Poin — jangan tertukar.
     */
    public function totalFor(int $userId): int
    {
        return (int) ExpTransaction::where('user_id', $userId)->sum('amount');
    }

    /**
     * Ambil semua transaksi yang berasal dari satu sumber tertentu.
     * Berguna untuk cek idempotency sebelum record() dipanggil ulang.
     */
    public function transactionsFrom(string $sourceType, int $sourceId): Collection
    {
        return ExpTransaction::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();
    }
}