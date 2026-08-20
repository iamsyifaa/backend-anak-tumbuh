<?php

namespace Tests\Feature\Report;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\SubmissionAnswer;
use App\Models\User;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitInitiativeReportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bikin 1 habit dengan 2 indikator: indikator utama (mis. Jam Bangun)
     * dan indikator "inisiatif" (Sadar sendiri / Disuruh) — sesuai pola
     * nyata di ScoringService (INITIATIVE_INDICATOR_CODE = 'inisiatif').
     */
    private function makeHabitWithIndicators(): array
    {
        $habit = Habit::create([
            'code' => 'bangun_pagi', 'name' => 'Bangun Pagi', 'sort_order' => 1, 'active' => true,
        ]);

        $mainIndicator = HabitIndicator::create([
            'habit_id' => $habit->id, 'code' => 'jam_bangun', 'label' => 'Jam Bangun',
            'is_required' => true, 'sort_order' => 1, 'active' => true,
        ]);
        $mainOption = IndicatorOption::create([
            'indicator_id' => $mainIndicator->id, 'label' => 'Bangun pukul 05.00', 'value' => 'pukul_05',
            'point_value' => 10, 'exp_value' => 5, 'sort_order' => 1, 'active' => true,
        ]);

        $initiativeIndicator = HabitIndicator::create([
            'habit_id' => $habit->id, 'code' => 'inisiatif', 'label' => 'Inisiatif',
            'is_required' => true, 'sort_order' => 2, 'active' => true,
        ]);
        $sadarSendiriOption = IndicatorOption::create([
            'indicator_id' => $initiativeIndicator->id, 'label' => 'Sadar sendiri', 'value' => 'sadar_sendiri',
            'point_value' => 0, 'exp_value' => 0, 'sort_order' => 1, 'active' => true,
        ]);
        $disuruhOption = IndicatorOption::create([
            'indicator_id' => $initiativeIndicator->id, 'label' => 'Disuruh', 'value' => 'disuruh',
            'point_value' => 0, 'exp_value' => 0, 'sort_order' => 2, 'active' => true,
        ]);

        return compact('habit', 'mainIndicator', 'mainOption', 'initiativeIndicator', 'sadarSendiriOption', 'disuruhOption');
    }

    private function makeRombel(): Rombel
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        return Rombel::create([
            'school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Kelas 5A',
        ]);
    }

    private function makeEnrolledStudent(Rombel $rombel, string $method = StudentProfile::METHOD_DIGITAL): StudentProfile
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa '.uniqid(), 'method' => $method,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id, 'academic_year_id' => $rombel->academic_year_id,
            'rombel_id' => $rombel->id, 'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        return $profile;
    }

    /**
     * Submit jawaban locked untuk 1 siswa di 1 tanggal, sekaligus catat
     * ledger poin/exp (meniru output ScoringService::scoreSubmission()
     * tanpa harus memanggil service itu penuh).
     *
     * @param  array<int, array{0: HabitIndicator, 1: IndicatorOption}>  $indicatorOptionPairs
     * @return array{submission: ActivitySubmission, answers: array<int, SubmissionAnswer>} answers keyed by indicator_id
     */
    private function submitAnswer(StudentProfile $profile, Carbon $date, array $indicatorOptionPairs): array
    {
        $submission = ActivitySubmission::create([
            'student_profile_id' => $profile->id,
            'activity_date' => $date->toDateString(),
            'submitted_at' => $date,
            'locked_at' => $date,
            'status' => 'locked',
        ]);

        $answers = [];

        foreach ($indicatorOptionPairs as [$indicator, $option]) {
            $answer = SubmissionAnswer::create([
                'activity_submission_id' => $submission->id,
                'indicator_id' => $indicator->id,
                'indicator_option_id' => $option->id,
            ]);

            $answers[$indicator->id] = $answer;

            PointTransaction::create([
                'user_id' => $profile->user_id, 'amount' => $option->point_value,
                'source_type' => 'submission_answer', 'source_id' => $answer->id,
                'period_date' => $date->toDateString(),
            ]);
            ExpTransaction::create([
                'user_id' => $profile->user_id, 'amount' => $option->exp_value,
                'source_type' => 'submission_answer', 'source_id' => $answer->id,
                'period_date' => $date->toDateString(),
            ]);
        }

        return ['submission' => $submission, 'answers' => $answers];
    }

    public function test_siswa_belum_mengisi_tetap_muncul_dengan_metode_master_data(): void
    {
        $rombel = $this->makeRombel();
        $digital = $this->makeEnrolledStudent($rombel, StudentProfile::METHOD_DIGITAL);
        $manual = $this->makeEnrolledStudent($rombel, StudentProfile::METHOD_MANUAL);

        ['habit' => $habit] = $this->makeHabitWithIndicators();

        $report = app(ReportService::class)->getHabitInitiativeReport(
            habitId: $habit->id,
            initiatives: [],
            rombelId: $rombel->id,
            schoolId: null,
            startDate: Carbon::now()->startOfMonth(),
            endDate: Carbon::now()->endOfMonth(),
        );

        $this->assertSame(2, $report['meta']['total_siswa']);
        $this->assertSame(1, $report['meta']['digital_count']);
        $this->assertSame(1, $report['meta']['manual_count']);

        $digitalRow = collect($report['data'])->firstWhere('student_id', $digital->id);
        $manualRow = collect($report['data'])->firstWhere('student_id', $manual->id);

        $this->assertSame('DIGITAL', $digitalRow['metode']);
        $this->assertSame('Belum mengisi', $digitalRow['deskripsi']);
        $this->assertSame('-', $digitalRow['poin']);

        $this->assertSame('MANUAL', $manualRow['metode']);
        $this->assertSame('Belum mengisi', $manualRow['deskripsi']);
    }

    public function test_filter_inisiatif_sadar_sendiri_hanya_menampilkan_yang_match_dan_menghitung_bonus(): void
    {
        $rombel = $this->makeRombel();
        ['habit' => $habit, 'mainIndicator' => $mainIndicator, 'mainOption' => $mainOption,
            'initiativeIndicator' => $initiativeIndicator, 'sadarSendiriOption' => $sadarSendiriOption,
            'disuruhOption' => $disuruhOption] = $this->makeHabitWithIndicators();

        $date = Carbon::now()->startOfMonth()->addDays(2);

        // Siswa A: sadar sendiri -> dapat bonus poin.
        $studentSadar = $this->makeEnrolledStudent($rombel);
        $result = $this->submitAnswer($studentSadar, $date, [
            [$mainIndicator, $mainOption],
            [$initiativeIndicator, $sadarSendiriOption],
        ]);
        PointTransaction::create([
            'user_id' => $studentSadar->user_id, 'amount' => 15,
            'source_type' => 'initiative_bonus', 'source_id' => $result['answers'][$initiativeIndicator->id]->id,
            'period_date' => $date->toDateString(),
        ]);

        // Siswa B: disuruh -> tidak boleh muncul saat filter = sadar_sendiri.
        $studentDisuruh = $this->makeEnrolledStudent($rombel);
        $this->submitAnswer($studentDisuruh, $date, [
            [$mainIndicator, $mainOption],
            [$initiativeIndicator, $disuruhOption],
        ]);

        $report = app(ReportService::class)->getHabitInitiativeReport(
            habitId: $habit->id,
            initiatives: ['sadar_sendiri'],
            rombelId: $rombel->id,
            schoolId: null,
            startDate: Carbon::now()->startOfMonth(),
            endDate: Carbon::now()->endOfMonth(),
        );

        $sadarRow = collect($report['data'])->firstWhere('student_id', $studentSadar->id);
        $disuruhRow = collect($report['data'])->firstWhere('student_id', $studentDisuruh->id);

        $this->assertSame('Sadar sendiri', $sadarRow['inisiatif']);
        $this->assertSame(25, $sadarRow['poin']); // 10 (base) + 15 (bonus)
        $this->assertSame(5, $sadarRow['exp']);

        // Siswa B tidak match filter -> dianggap tidak ada data yang lolos, jadi "Belum mengisi".
        $this->assertSame('Belum mengisi', $disuruhRow['deskripsi']);
    }

    public function test_active_days_dihitung_dari_hari_unik_submission_bukan_total_range(): void
    {
        $rombel = $this->makeRombel();
        ['habit' => $habit, 'mainIndicator' => $mainIndicator, 'mainOption' => $mainOption,
            'initiativeIndicator' => $initiativeIndicator, 'sadarSendiriOption' => $sadarSendiriOption]
            = $this->makeHabitWithIndicators();

        $student = $this->makeEnrolledStudent($rombel);
        $start = Carbon::now()->startOfMonth();

        // Submit di 3 hari saja dari rentang 10 hari.
        foreach ([0, 1, 2] as $offset) {
            $this->submitAnswer($student, $start->copy()->addDays($offset), [
                [$mainIndicator, $mainOption],
                [$initiativeIndicator, $sadarSendiriOption],
            ]);
        }

        $report = app(ReportService::class)->getHabitInitiativeReport(
            habitId: $habit->id,
            initiatives: [],
            rombelId: $rombel->id,
            schoolId: null,
            startDate: $start,
            endDate: $start->copy()->addDays(9),
        );

        $this->assertSame(3, $report['meta']['active_days']);

        $row = collect($report['data'])->firstWhere('student_id', $student->id);
        $this->assertSame(100, $row['persentase']); // 3 hari isi habit ini / 3 active_days
    }
}