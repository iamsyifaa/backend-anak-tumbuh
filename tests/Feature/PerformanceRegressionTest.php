<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rombel_report_query_count_does_not_scale_with_student_count(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $rombel = Rombel::create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Kelas 1A']);

        // Buat 5 siswa — kalau ada N+1, query akan ikut naik 5x lipat.
        foreach (range(1, 5) as $i) {
            $user = User::factory()->create();
            $profile = StudentProfile::create([
                'user_id' => $user->id, 'full_name' => 'Siswa '.$i, 'method' => StudentProfile::METHOD_DIGITAL,
                'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
                'nisn' => (string) rand(1000000000, 9999999999),
            ]);
            Enrollment::create([
                'student_profile_id' => $profile->id, 'academic_year_id' => $academicYear->id,
                'rombel_id' => $rombel->id, 'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
            ]);
            PointTransaction::create([
                'user_id' => $user->id, 'amount' => 10, 'source_type' => 'submission_answer',
                'source_id' => $i, 'period_date' => now()->toDateString(),
            ]);
        }

        DB::enableQueryLog();

        app(ReportService::class)->getRombelReport($rombel->id, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Batas wajar TIDAK scale linear dengan jumlah siswa (5 siswa).
        // Kalau N+1 masih ada, ini akan jauh di atas 10 (minimal 15+).
        $this->assertLessThan(10, $queryCount, "Query count {$queryCount} — kemungkinan N+1 muncul lagi.");
    }

    public function test_answer_validation_query_count_does_not_scale_with_answer_count(): void
    {
        $habit = \App\Models\Habit::create(['code' => 'habit_'.uniqid(), 'name' => 'Kebiasaan Test']);

        $answers = [];
        foreach (range(1, 7) as $i) {
            $indicator = \App\Models\HabitIndicator::create([
                'habit_id' => $habit->id, 'code' => 'ind_'.uniqid(), 'label' => 'Indikator '.$i,
                'is_required' => true, 'sort_order' => $i, 'active' => true,
            ]);
            $option = \App\Models\IndicatorOption::create([
                'indicator_id' => $indicator->id, 'label' => 'Opsi', 'value' => 'sudah',
                'point_value' => 10, 'sort_order' => 1, 'active' => true,
            ]);
            $answers[$indicator->id] = $option->id;
        }

        DB::enableQueryLog();

        app(\App\Services\AnswerEngine\AnswerValidationService::class)->validate($answers);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 7 jawaban, tapi harus tetap cuma sekitar 2 query (condition + option),
        // bukan 7+ (yang berarti N+1 masih ada).
        $this->assertLessThan(5, $queryCount, "Query count {$queryCount} untuk 7 jawaban — kemungkinan N+1 muncul lagi.");
    }
}