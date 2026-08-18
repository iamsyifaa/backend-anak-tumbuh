<?php

namespace Tests\Unit;

use App\Models\ActivitySubmission;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Comment\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(): ActivitySubmission
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa Test', 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        return ActivitySubmission::create([
            'student_profile_id' => $profile->id, 'activity_date' => now()->toDateString(), 'status' => 'draft',
        ]);
    }

    public function test_teacher_can_start_a_comment(): void
    {
        $submission = $this->makeSubmission();
        $teacher = User::factory()->create(['role' => 'wali_kelas']);

        $comment = app(CommentService::class)->post($submission, $teacher->id, 'Bagus sekali hari ini!');

        $this->assertNull($comment->parent_id);
        $this->assertSame('Bagus sekali hari ini!', $comment->body);
    }

    public function test_student_can_reply_to_teacher_comment(): void
    {
        $submission = $this->makeSubmission();
        $teacher = User::factory()->create(['role' => 'wali_kelas']);
        $service = app(CommentService::class);

        $parent = $service->post($submission, $teacher->id, 'Bagus sekali!');
        $reply = $service->post($submission, $submission->studentProfile->user_id, 'Terima kasih Bu!', $parent->id);

        $this->assertTrue($reply->isReply());
        $this->assertSame($parent->id, $reply->parent_id);
    }

    public function test_reply_to_nonexistent_parent_throws_exception(): void
    {
        $submission = $this->makeSubmission();

        $this->expectException(\InvalidArgumentException::class);

        app(CommentService::class)->post($submission, $submission->studentProfile->user_id, 'Test', 99999);
    }

    public function test_thread_returns_top_level_with_nested_replies(): void
    {
        $submission = $this->makeSubmission();
        $teacher = User::factory()->create(['role' => 'wali_kelas']);
        $service = app(CommentService::class);

        $parent = $service->post($submission, $teacher->id, 'Komentar utama');
        $service->post($submission, $submission->studentProfile->user_id, 'Balasan 1', $parent->id);

        $thread = $service->getThreadForSubmission($submission);

        $this->assertCount(1, $thread);
        $this->assertCount(1, $thread->first()->replies);
    }
}
