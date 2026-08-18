<?php

namespace App\Queries;

use App\Models\PointTransaction;

class StudentPointQuery
{
    public function totalPoints(int $userId): int
    {
        return (int) PointTransaction::query()
            ->where('user_id', $userId)
            ->sum('amount');
    }
}
