<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi & pemberian badge otomatis berdasarkan TARGET PENCAPAIAN
 * (bukan streak — sesuai requirement eksplisit). Dipanggil setelah ada
 * perubahan poin/EXP siswa (mis. dari PointCalculationService atau
 * ExpCalculationService — cek dulu apakah service EXP sudah ada di
 * project sebelum disambungkan, kemungkinan besar milik Anggota C).
 *
 * Desain generic (target_type) supaya jenis target baru bisa ditambah
 * tanpa migration/model baru, cukup tambah 1 case di getMetricValue().
 */
class BadgeEvaluationService
{
    public function checkAndAwardBadges(StudentProfile $studentProfile): array
    {
        return DB::transaction(function () use ($studentProfile) {
            $alreadyEarnedIds = StudentBadge::where('student_profile_id', $studentProfile->id)
                ->pluck('badge_id')
                ->all();

            $candidateBadges = Badge::where('active', true)
                ->whereNotIn('id', $alreadyEarnedIds)
                ->get();

            $newlyAwarded = [];

            foreach ($candidateBadges as $badge) {
                $currentValue = $this->getMetricValue($studentProfile, $badge->target_type);

                if ($currentValue >= $badge->target_value) {
                    StudentBadge::create([
                        'student_profile_id' => $studentProfile->id,
                        'badge_id' => $badge->id,
                        'awarded_at' => now(),
                    ]);

                    $newlyAwarded[] = $badge;
                }
            }

            return $newlyAwarded;
        });
    }

    private function getMetricValue(StudentProfile $studentProfile, string $targetType): int
    {
        $userId = $studentProfile->user_id;

        return match ($targetType) {
            Badge::TARGET_TOTAL_POINTS => (int) PointTransaction::where('user_id', $userId)->sum('amount'),
            Badge::TARGET_TOTAL_EXP => class_exists(ExpTransaction::class)
                ? (int) ExpTransaction::where('user_id', $userId)->sum('amount')
                : 0,
            default => 0,
        };
    }
}
