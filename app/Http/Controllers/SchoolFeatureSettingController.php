<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolFeatureSetting\SchoolFeatureSettingRequest;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SchoolFeatureSettingController extends Controller
{
    use ApiResponse;

    public function show(Request $request, School $school)
    {
        $setting = $school->featureSetting ?? $school->featureSetting()->create([]);

        $this->authorize('view', $setting);

        return $this->success($setting);
    }

    public function update(SchoolFeatureSettingRequest $request, School $school)
    {
        $setting = $school->featureSetting ?? $school->featureSetting()->create([]);

        $this->authorize('update', $setting);

        $setting->update($request->validated());

        return $this->success($setting, 'Pengaturan fitur berhasil diperbarui.');
    }
}