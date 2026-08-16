<?php

namespace Tests\Unit;

use App\Models\ActivitySubmission;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Progress\ProgressService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_counts_locked_submissions_this_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10'));

        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa Test', 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        ActivitySubmission::create(['student_profile_id' => $profile->id, 'activity_date' => '2026-08-01', 'status' => 'locked']);
        ActivitySubmission::create(['student_profile_id' => $profile->id, 'activity_date' => '2026-08-02', 'status' => 'locked']);
        ActivitySubmission::create(['student_profile_id' => $profile->id, 'activity_date' => '2026-08-03', 'status' => 'draft']); // belum locked

        $progress = app(ProgressService::class)->getMonthlyProgress($profile->id);

        $this->assertSame(2, $progress['submitted_days']);
        $this->assertSame(10, $progress['days_elapsed']);

        Carbon::setTestNow();
    }
}