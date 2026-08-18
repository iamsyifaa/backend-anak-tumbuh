<?php

namespace App\Queries;

use App\Models\ExpTransaction;

class StudentExpQuery
{
    public function totalExp(int $userId): int
    {
        return (int) ExpTransaction::query()
            ->where('user_id', $userId)
            ->sum('amount');
    }
}
