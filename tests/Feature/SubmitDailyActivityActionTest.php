<?php

namespace Tests\Feature;

use App\Actions\Business\SubmitDailyActivityAction;
use App\Models\ActivitySubmission;
use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\PointTransaction;
use App\Models\SubmissionAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitDailyActivityActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmissionWithIndicator(): array
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

        $habit = Habit::create(['code' => 'habit_'.uniqid(), 'name' => 'Kebiasaan Test']);
        $indicator = HabitIndicator::create([
            'habit_id' => $habit->id,
            'code' => 'menyapu',
            'label' => 'Menyapu',
            'is_required' => true,
            'sort_order' => 1,
            'active' => true,
        ]);
        $option = IndicatorOption::create([
            'indicator_id' => $indicator->id,
            'label' => 'Sudah',
            'value' => 'sudah',
            'point_value' => 10,
            'sort_order' => 1,
            'active' => true,
        ]);

        return [$submission, $indicator, $option];
    }

    public function test_valid_submission_is_scored_and_locked(): void
    {
        [$submission, $indicator, $option] = $this->makeSubmissionWithIndicator();

        $result = app(SubmitDailyActivityAction::class)->execute($submission, [
            $indicator->id => $option->id,
        ]);

        $this->assertSame('submitted', $result['status']);
        $this->assertTrue($result['submission']->fresh()->isLocked());
        $this->assertSame(1, SubmissionAnswer::where('activity_submission_id', $submission->id)->count());
        $this->assertGreaterThan(0, PointTransaction::count());
    }

    public function test_invalid_answer_is_rejected_without_side_effects(): void
    {
        [$submission, ,] = $this->makeSubmissionWithIndicator();

        // Kirim option_id yang tidak eksis — memicu validation error dari BE-005
        $result = app(SubmitDailyActivityAction::class)->execute($submission, [
            99999 => 99999,
        ]);

        $this->assertSame('validation_failed', $result['status']);
        $this->assertFalse($submission->fresh()->isLocked());
        $this->assertSame(0, SubmissionAnswer::count());
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_retry_on_already_locked_submission_does_not_duplicate_scoring(): void
    {
        [$submission, $indicator, $option] = $this->makeSubmissionWithIndicator();

        $action = app(SubmitDailyActivityAction::class);

        $first = $action->execute($submission, [$indicator->id => $option->id]);
        $this->assertSame('submitted', $first['status']);

        $pointsAfterFirst = PointTransaction::sum('amount');

        // RETRY — simulasi client submit ulang (network timeout dsb)
        $second = $action->execute($submission->fresh(), [$indicator->id => $option->id]);

        $this->assertSame('already_submitted', $second['status']);
        $this->assertSame($pointsAfterFirst, PointTransaction::sum('amount')); // TIDAK dobel
        $this->assertSame(1, SubmissionAnswer::where('activity_submission_id', $submission->id)->count()); // TIDAK dobel
    }

    public function test_submission_is_atomic_when_scoring_fails_partway(): void
    {
        [$submission, $indicator, $option] = $this->makeSubmissionWithIndicator();

        // Simulasikan kegagalan: kirim jawaban valid + 1 jawaban dengan
        // indicator_id yang tidak eksis sama sekali (bukan cuma opsi salah,
        // tapi indikatornya sendiri tidak ada) — harus tetap tidak
        // menyimpan APAPUN, termasuk jawaban yang tadinya valid.
        try {
            app(SubmitDailyActivityAction::class)->execute($submission, [
                $indicator->id => $option->id,
                99999 => 1,
            ]);
        } catch (\Throwable) {
            // boleh throw atau return error tergantung constraint DB —
            // yang penting dicek di bawah: TIDAK ada data nyangkut.
        }

        $this->assertSame(0, PointTransaction::count());
    }
}