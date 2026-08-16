<?php

namespace App\Services\Comment;

use App\Models\ActivityComment;
use App\Models\ActivitySubmission;

class CommentService
{
    public function post(ActivitySubmission $submission, int $userId, string $body, ?int $parentId = null): ActivityComment
    {
        if ($parentId !== null) {
            $parent = ActivityComment::where('id', $parentId)
                ->where('activity_submission_id', $submission->id)
                ->first();

            if (! $parent) {
                throw new \InvalidArgumentException('Comment induk tidak ditemukan pada aktivitas ini.');
            }
        }

        return ActivityComment::create([
            'activity_submission_id' => $submission->id,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
        ]);
    }

    public function getThreadForSubmission(ActivitySubmission $submission)
    {
        return ActivityComment::with(['user', 'replies.user'])
            ->where('activity_submission_id', $submission->id)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->get();
    }
}