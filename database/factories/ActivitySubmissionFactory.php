<?php

namespace Database\Factories;

use App\Models\ActivitySubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivitySubmissionFactory extends Factory
{
    protected $model = ActivitySubmission::class;

    public function definition(): array
    {
        return [
            // student_profile_id sengaja TIDAK auto-generate via factory relation
            // di sini karena StudentProfile dimiliki Anggota B dan strukturnya
            // (kolom wajib selain user_id) belum sepenuhnya saya ketahui — test
            // WAJIB pass 'student_profile_id' eksplisit dari StudentProfile yang
            // sudah dibuat di test itu sendiri.
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => 'locked',
            'submitted_at' => now(),
            'locked_at' => now(),
        ]);
    }
}