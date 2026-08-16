<?php

namespace App\Policies;

use App\Models\ActivityComment;
use App\Models\ActivitySubmission;
use App\Models\User;

class ActivityCommentPolicy
{
    public function create(User $user, ActivitySubmission $submission): bool
    {
        return $this->canAccessSubmission($user, $submission);
    }

    public function view(User $user, ActivityComment $comment): bool
    {
        return $this->canAccessSubmission($user, $comment->activitySubmission);
    }

    private function canAccessSubmission(User $user, ActivitySubmission $submission): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'siswa') {
            return $user->studentProfile?->id === $submission->student_profile_id;
        }

        if ($user->role === 'wali_kelas') {
            $studentRombelId = $submission->studentProfile
                ->currentEnrollment()->first()?->rombel_id;

            $teacherRombelId = $user->teacherRombelAssignments()
                ->where('status', 'active')
                ->value('rombel_id');

            return $studentRombelId !== null && $studentRombelId === $teacherRombelId;
        }

        if ($user->role === 'kepala_sekolah') {
            $studentSchoolId = $submission->studentProfile
                ->currentEnrollment()->first()?->academicYear?->school_id;

            return $studentSchoolId === $user->school_id;
        }

        return false;
    }
}