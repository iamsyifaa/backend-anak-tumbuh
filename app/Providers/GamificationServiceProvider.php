<?php

namespace App\Providers;

use App\Models\StudentAward;
use App\Observers\StudentAwardObserver;
use Illuminate\Support\ServiceProvider;

class GamificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        StudentAward::observe(StudentAwardObserver::class);
    }
}
