<?php

namespace App\Services\Report;

use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\SubmissionAnswer;
use App\Services\Analytics\SchoolAnalyticsService;
use App\Services\Business\LevelService;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        private SchoolAnalyticsService $analyticsService,
        private LevelService $levelService,
    ) {}

    public function getStudentReport(StudentProfile $studentProfile, Carbon $startDate, Carbon $endDate): array
    {
        $userId = $studentProfile->user_id;

        $totalPoints = (int) PointTransaction::where('user_id', $userId)
            ->whereBetween('period_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $totalExp = (int) ExpTransaction::where('user_id', $userId)
            ->whereBetween('period_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $submittedDays = ActivitySubmission::where('student_profile_id', $studentProfile->id)
            ->where('status', 'locked')
            ->whereBetween('activity_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        return [
            'student_profile_id' => $studentProfile->id,
            'full_name' => $studentProfile->full_name,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'total_points' => $totalPoints,
            'total_exp' => $totalExp,
            'level' => $this->levelService->calculateLevel($totalExp),
            'submitted_days' => $submittedDays,
        ];
    }

    /**
     * PERFORMANCE FIX: dulu memanggil getStudentReport() per siswa di dalam
     * loop — untuk N siswa jadi 3×N query (Poin, EXP, submitted_days masing-
     * masing per siswa). Sekarang 3 query TOTAL untuk seluruh rombel
     * (masing-masing 1 query ber-GROUP BY), digabung di memori.
     */
    public function getRombelReport(int $rombelId, Carbon $startDate, Carbon $endDate): array
    {
        $students = StudentProfile::whereHas(
            'currentEnrollment',
            fn ($q) => $q->where('rombel_id', $rombelId)->where('status', 'active')
        )->get();

        if ($students->isEmpty()) {
            return [
                'rombel_id' => $rombelId,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'student_count' => 0,
                'students' => [],
            ];
        }

        $userIds = $students->pluck('user_id');
        $studentProfileIds = $students->pluck('id');
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        $pointsByUser = PointTransaction::whereIn('user_id', $userIds)
            ->whereBetween('period_date', [$startStr, $endStr])
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $expByUser = ExpTransaction::whereIn('user_id', $userIds)
            ->whereBetween('period_date', [$startStr, $endStr])
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $submittedByProfile = ActivitySubmission::whereIn('student_profile_id', $studentProfileIds)
            ->where('status', 'locked')
            ->whereBetween('activity_date', [$startStr, $endStr])
            ->selectRaw('student_profile_id, COUNT(*) as total')
            ->groupBy('student_profile_id')
            ->get()
            ->keyBy('student_profile_id');

        $rows = $students->map(function (StudentProfile $sp) use ($pointsByUser, $expByUser, $submittedByProfile, $startDate, $endDate) {
            $totalPoints = (int) ($pointsByUser->get($sp->user_id)->total ?? 0);
            $totalExp = (int) ($expByUser->get($sp->user_id)->total ?? 0);

            return [
                'student_profile_id' => $sp->id,
                'full_name' => $sp->full_name,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'total_points' => $totalPoints,
                'total_exp' => $totalExp,
                'level' => $this->levelService->calculateLevel($totalExp),
                'submitted_days' => (int) ($submittedByProfile->get($sp->id)->total ?? 0),
            ];
        });

        return [
            'rombel_id' => $rombelId,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'student_count' => $rows->count(),
            'students' => $rows->values()->toArray(),
        ];
    }

    /**
     * Report Filter Kebiasaan & Inisiatif — Wali Kelas / Kepala Sekolah.
     * Base data = SELURUH siswa aktif di scope (bukan dari submission),
     * supaya siswa yang belum mengisi tetap muncul dengan status
     * "Belum mengisi" dan metode (Digital/Manual) dari master data.
     *
     * @param  string[]  $initiatives  ['sadar_sendiri', 'disuruh'] atau [] = semua
     */
    public function getHabitInitiativeReport(
        int $habitId,
        array $initiatives,
        ?int $rombelId,
        ?int $schoolId,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        // 1. Base: seluruh StudentProfile aktif di scope (rombel atau sekolah).
        $students = StudentProfile::query()
            ->where('status', StudentProfile::STATUS_ACTIVE)
            ->whereHas('currentEnrollment', function ($q) use ($rombelId, $schoolId) {
                if ($rombelId) {
                    $q->where('rombel_id', $rombelId);
                } elseif ($schoolId) {
                    $q->whereHas('rombel', fn ($r) => $r->where('school_id', $schoolId));
                }
            })
            ->with(['currentEnrollment.rombel.school'])
            ->get();

        if ($students->isEmpty()) {
            return [
                'meta' => ['total_siswa' => 0, 'digital_count' => 0, 'manual_count' => 0, 'active_days' => 0],
                'data' => [],
            ];
        }

        $studentProfileIds = $students->pluck('id');

        // 2. active_days = jumlah hari unik ada submission locked dalam scope+range.
        $activeDays = ActivitySubmission::whereIn('student_profile_id', $studentProfileIds)
            ->where('status', 'locked')
            ->whereBetween('activity_date', [$startStr, $endStr])
            ->distinct('activity_date')
            ->count('activity_date');

        // 3. Jawaban untuk habit ini, dari submission locked dalam range & scope.
        $answers = SubmissionAnswer::query()
            ->join('activity_submissions', 'activity_submissions.id', '=', 'submission_answers.activity_submission_id')
            ->join('habit_indicators', 'habit_indicators.id', '=', 'submission_answers.indicator_id')
            ->where('habit_indicators.habit_id', $habitId)
            ->where('activity_submissions.status', 'locked')
            ->whereIn('activity_submissions.student_profile_id', $studentProfileIds)
            ->whereBetween('activity_submissions.activity_date', [$startStr, $endStr])
            ->select('submission_answers.*')
            ->with(['indicator', 'option', 'activitySubmission'])
            ->get()
            ->groupBy(fn (SubmissionAnswer $a) =>
                $a->activitySubmission->student_profile_id.'|'.$a->activitySubmission->activity_date->toDateString()
            );

        // 4. Per (siswa, tanggal): tentukan inisiatif + deskripsi, buang yang tidak lolos filter inisiatif.
        $perStudentDays = collect();

        foreach ($answers as $key => $dayAnswers) {
            [$studentProfileId, $date] = explode('|', $key);

            $initiativeAnswer = $dayAnswers->first(fn ($a) => $a->indicator->code === 'inisiatif');
            $initiativeValue = $initiativeAnswer?->option->value;

            if (! empty($initiatives) && ! in_array($initiativeValue, $initiatives, true)) {
                continue;
            }

            $mainAnswer = $dayAnswers->first(fn ($a) => $a->indicator->code !== 'inisiatif');

            $perStudentDays->push([
                'student_profile_id' => (int) $studentProfileId,
                'date' => $date,
                'description' => $mainAnswer?->option->label ?? $initiativeAnswer?->option->label,
                'initiative_label' => $initiativeAnswer
                    ? ($initiativeValue === 'sadar_sendiri' ? 'Sadar sendiri' : 'Disuruh')
                    : null,
                'answer_ids' => $dayAnswers->pluck('id'),
                'initiative_answer_id' => $initiativeAnswer?->id,
            ]);
        }

        $matchedByStudent = $perStudentDays->groupBy('student_profile_id');

        // 5. Poin & EXP dari ledger — hanya untuk answer_id yang match filter.
        $allAnswerIds = $perStudentDays->flatMap(fn ($d) => $d['answer_ids'])->unique();
        $allBonusIds = $perStudentDays->pluck('initiative_answer_id')->filter()->unique();

        $pointsByAnswer = PointTransaction::where('source_type', 'submission_answer')
            ->whereIn('source_id', $allAnswerIds)->pluck('amount', 'source_id');

        $bonusByAnswer = PointTransaction::where('source_type', 'initiative_bonus')
            ->whereIn('source_id', $allBonusIds)->pluck('amount', 'source_id');

        $expByAnswer = ExpTransaction::where('source_type', 'submission_answer')
            ->whereIn('source_id', $allAnswerIds)->pluck('amount', 'source_id');

        // 6. Susun output: base = SEMUA siswa, left-merge hasil di atas.
        $data = $students->map(function (StudentProfile $sp) use (
            $matchedByStudent, $pointsByAnswer, $bonusByAnswer, $expByAnswer, $activeDays
        ) {
            $rombel = $sp->currentEnrollment->first()?->rombel;
            $rombelLabel = $rombel ? "{$rombel->name} • {$rombel->school->name}" : null;

            $days = $matchedByStudent->get($sp->id);

            if (blank($days)) {
                return [
                    'student_id' => $sp->id,
                    'nama' => $sp->full_name,
                    'rombel' => $rombelLabel,
                    'metode' => strtoupper($sp->method),
                    'persentase' => null,
                    'deskripsi' => 'Belum mengisi',
                    'inisiatif' => '-',
                    'poin' => '-',
                    'exp' => '-',
                ];
            }

            [$totalPoin, $totalExp] = [0, 0];
            foreach ($days as $day) {
                foreach ($day['answer_ids'] as $answerId) {
                    $totalPoin += $pointsByAnswer[$answerId] ?? 0;
                    $totalExp += $expByAnswer[$answerId] ?? 0;
                }
                if ($day['initiative_answer_id']) {
                    $totalPoin += $bonusByAnswer[$day['initiative_answer_id']] ?? 0;
                }
            }

            $latest = $days->sortByDesc('date')->first();

            return [
                'student_id' => $sp->id,
                'nama' => $sp->full_name,
                'rombel' => $rombelLabel,
                'metode' => strtoupper($sp->method),
                'persentase' => $activeDays > 0 ? (int) round(($days->count() / $activeDays) * 100) : null,
                'deskripsi' => $latest['description'] ?? '-',
                'inisiatif' => $latest['initiative_label'] ?? '-',
                'poin' => $totalPoin,
                'exp' => $totalExp,
            ];
        });

        return [
            'meta' => [
                'total_siswa' => $students->count(),
                'digital_count' => $students->where('method', StudentProfile::METHOD_DIGITAL)->count(),
                'manual_count' => $students->where('method', StudentProfile::METHOD_MANUAL)->count(),
                'active_days' => $activeDays,
            ],
            'data' => $data->values()->all(),
        ];
    }

    public function getSchoolReport(int $schoolId, int $trendDays = 30): array
    {
        return [
            'school_id' => $schoolId,
            'average_points' => $this->analyticsService->getSchoolAveragePoints($schoolId),
            'rombel_achievements' => $this->analyticsService->getRombelAchievements($schoolId)->toArray(),
            'trend' => $this->analyticsService->getSchoolTrend($schoolId, $trendDays),
        ];
    }

    public function getAchievementReport(StudentProfile $studentProfile, Carbon $startDate, Carbon $endDate): array
    {
        $badges = StudentBadge::with('badge')
            ->where('student_profile_id', $studentProfile->id)
            ->whereBetween('awarded_at', [$startDate, $endDate])
            ->get()
            ->map(fn ($sb) => ['type' => 'badge', 'name' => $sb->badge->name, 'date' => $sb->awarded_at]);

        $awards = StudentAward::with('award')
            ->where('student_profile_id', $studentProfile->id)
            ->whereBetween('given_at', [$startDate, $endDate])
            ->get()
            ->map(fn ($sa) => ['type' => 'award', 'name' => $sa->award->name, 'date' => $sa->given_at]);

        return [
            'student_profile_id' => $studentProfile->id,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'badges_earned' => $badges->count(),
            'awards_received' => $awards->count(),
            'achievements' => $badges->concat($awards)->sortBy('date')->values()->toArray(),
        ];
    }
}