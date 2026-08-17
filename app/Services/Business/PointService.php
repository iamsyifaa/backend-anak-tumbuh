<?php

namespace App\Services\Business;

use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Collection;

class PointService
{
    public function __construct() {}

    /**
     * Catat satu transaksi Poin untuk user tertentu.
     *
     * Tabel point_transactions bersifat insert-only (tidak ada updated_at)
     * agar histori transaksi tidak pernah berubah walau konfigurasi
     * Poin berubah di kemudian hari (immutable history principle).
     *
     * @param  int         $userId
     * @param  int         $amount      Bisa positif (dapat poin) atau negatif (koreksi/penalti).
     * @param  string      $sourceType  Contoh: 'submission_answer'.
     * @param  int         $sourceId    ID baris sumber, contoh: id submission_answers.
     * @param  string|\DateTimeInterface $periodDate  Tanggal periode harian saat poin didapat.
     */
    public function record(
        int $userId,
        int $amount,
        string $sourceType,
        int $sourceId,
        string|\DateTimeInterface $periodDate
    ): PointTransaction {
        return PointTransaction::create([
            'user_id' => $userId,
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'period_date' => $periodDate,
        ]);
    }

    /**
     * Total Poin milik user (SUM semua transaksi). Dipakai untuk dashboard,
     * ranking, dsb. Ranking WAJIB pakai Poin, bukan EXP.
     */
    public function totalFor(int $userId): int
    {
        return (int) PointTransaction::where('user_id', $userId)->sum('amount');
    }

    /**
     * Ambil semua transaksi yang berasal dari satu sumber tertentu.
     * Berguna untuk cek idempotency (mis. "sudah pernah dicatat belum
     * untuk submission_answer ini") sebelum record() dipanggil ulang.
     */
    public function transactionsFrom(string $sourceType, int $sourceId): Collection
    {
        return PointTransaction::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();
    }
}