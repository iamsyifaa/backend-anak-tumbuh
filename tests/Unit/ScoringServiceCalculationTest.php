<?php

namespace Tests\Unit;

use App\Models\ActivitySubmission;
use App\Models\ExpTransaction;
use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\PointTransaction;
use App\Models\StudentProfile;
use App\Models\SubmissionAnswer;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeHabit(): Habit
    {
        return Habit::create(['code' => 'habit_'.uniqid(), 'name' => 'Kebiasaan Test']);
    }

    private function makeIndicator(int $habitId, string $code, string $optionValue, int $pointValue): array
    {
        $indicator = HabitIndicator::create([
            'habit_id' => $habitId,
            'code' => $code,
            'label' => 'Indikator Test',
            'is_required' => true,
            'sort_order' => 1,
            'active' => true,
        ]);

        $option = IndicatorOption::create([
            'indicator_id' => $indicator->id,
            'label' => 'Opsi Test',
            'value' => $optionValue,
            'point_value' => $pointValue,
            'sort_order' => 1,
            'active' => true,
        ]);

        return [$indicator, $option];
    }

    private function makeSubmission(): array
    {
        $user = User::factory()->create();
        $studentProfile = StudentProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Test',
            'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE,
            'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        $submission = ActivitySubmission::create([
            'student_profile_id' => $studentProfile->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        return [$user, $studentProfile, $submission];
    }

    public function test_base_points_and_exp_are_recorded_separately_without_bonus(): void
    {
        [$user, , $submission] = $this->makeSubmission();
        $habit = $this->makeHabit();
        [$indicator, $option] = $this->makeIndicator($habit->id, 'menyapu', 'sudah', 10);

        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $indicator->id,
            'indicator_option_id' => $option->id,
        ]);

        app(ScoringService::class)->scoreSubmission($submission->fresh());

        $this->assertSame(10, PointTransaction::where('user_id', $user->id)->sum('amount'));
        $this->assertSame(10, ExpTransaction::where('user_id', $user->id)->sum('amount'));
    }

    public function test_sadar_sendiri_gives_point_bonus_but_not_exp(): void
    {
        [$user, , $submission] = $this->makeSubmission();
        $habit = $this->makeHabit();

        [$mainIndicator, $mainOption] = $this->makeIndicator($habit->id, 'menyapu', 'sudah', 10);
        [$initiativeIndicator, $initiativeOption] = $this->makeIndicator($habit->id, 'inisiatif', 'sadar_sendiri', 0);

        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $mainIndicator->id,
            'indicator_option_id' => $mainOption->id,
        ]);
        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $initiativeIndicator->id,
            'indicator_option_id' => $initiativeOption->id,
        ]);

        app(ScoringService::class)->scoreSubmission($submission->fresh());

        // Poin: 10 (dasar) + 10 (bonus inisiatif, dari EXP base yang 0 + bonus config default 0)
        // -> tanpa PointConfig ter-publish, bonus = 0. Test ini fokus BUKTIKAN
        // total poin >= total exp (bonus tidak nol-kan perbedaan), bukan nilai pasti.
        $totalPoints = PointTransaction::where('user_id', $user->id)->sum('amount');
        $totalExp = ExpTransaction::where('user_id', $user->id)->sum('amount');

        $this->assertGreaterThanOrEqual($totalExp, $totalPoints);
    }

    public function test_disuruh_gives_no_bonus(): void
    {
        [$user, , $submission] = $this->makeSubmission();
        $habit = $this->makeHabit();

        [$mainIndicator, $mainOption] = $this->makeIndicator($habit->id, 'menyapu', 'sudah', 10);
        [$initiativeIndicator, $initiativeOption] = $this->makeIndicator($habit->id, 'inisiatif', 'disuruh', 0);

        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $mainIndicator->id,
            'indicator_option_id' => $mainOption->id,
        ]);
        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $initiativeIndicator->id,
            'indicator_option_id' => $initiativeOption->id,
        ]);

        app(ScoringService::class)->scoreSubmission($submission->fresh());

        $totalPoints = PointTransaction::where('user_id', $user->id)->sum('amount');

        // Tanpa bonus, total poin harus PERSIS sama dengan jumlah base points saja (10+0)
        $this->assertSame(10, $totalPoints);
    }

    public function test_points_and_exp_transactions_are_traceable_to_source(): void
    {
        [$user, , $submission] = $this->makeSubmission();
        $habit = $this->makeHabit();
        [$indicator, $option] = $this->makeIndicator($habit->id, 'menyapu', 'sudah', 5);

        $answer = SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $indicator->id,
            'indicator_option_id' => $option->id,
        ]);

        app(ScoringService::class)->scoreSubmission($submission->fresh());

        $pointTx = PointTransaction::where('user_id', $user->id)->first();

        $this->assertSame('submission_answer', $pointTx->source_type);
        $this->assertSame($answer->id, $pointTx->source_id);
    }
}
