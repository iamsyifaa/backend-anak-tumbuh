<?php

namespace App\Http\Controllers;

use App\Http\Requests\EducationLevel\StoreEducationLevelRequest;
use App\Http\Requests\EducationLevel\UpdateEducationLevelRequest;
use App\Models\EducationLevel;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class EducationLevelController extends Controller
{
    use ApiResponse;

    public function index(Request $request, School $school)
    {
        $this->authorize('viewAny', [EducationLevel::class, $school]);

        return $this->success(
            $school->educationLevels()->orderBy('order')->get()
        );
    }

    public function store(StoreEducationLevelRequest $request, School $school)
    {
        $this->authorize('create', [EducationLevel::class, $school]);

        $educationLevel = $school->educationLevels()->create($request->validated());

        return $this->success($educationLevel, 'Jenjang pendidikan berhasil dibuat.', 201);
    }

    public function show(Request $request, School $school, EducationLevel $educationLevel)
    {
        $this->ensureBelongsToSchool($school, $educationLevel);
        $this->authorize('view', $educationLevel);

        return $this->success($educationLevel);
    }

    public function update(UpdateEducationLevelRequest $request, School $school, EducationLevel $educationLevel)
    {
        $this->ensureBelongsToSchool($school, $educationLevel);
        $this->authorize('update', $educationLevel);

        $educationLevel->update($request->validated());

        return $this->success($educationLevel, 'Jenjang pendidikan berhasil diperbarui.');
    }

    public function destroy(Request $request, School $school, EducationLevel $educationLevel)
    {
        $this->ensureBelongsToSchool($school, $educationLevel);
        $this->authorize('delete', $educationLevel);

        $educationLevel->delete();

        return $this->success(null, 'Jenjang pendidikan berhasil dihapus.');
    }

    /**
     * Dicek SEBELUM authorize() supaya education_level dari sekolah lain
     * balikin 404, bukan 403 — mencegah kebocoran informasi "resource ini
     * ada tapi bukan milikmu". Pola sama seperti AcademicYearController.
     */
    private function ensureBelongsToSchool(School $school, EducationLevel $educationLevel): void
    {
        abort_if($educationLevel->school_id !== $school->id, 404);
    }
}