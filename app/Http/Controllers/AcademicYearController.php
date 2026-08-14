<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYear\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Models\School;
use App\Services\AcademicYearService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AcademicYearService $academicYearService)
    {
    }

    public function index(Request $request, School $school)
    {
        $this->authorize('viewAny', [AcademicYear::class, $school]);

        return $this->success(
            $school->academicYears()->orderByDesc('start_date')->get()
        );
    }

    /**
     * Status default 'inactive' — pengaktifan eksplisit lewat activate() agar
     * aturan "hanya 1 tahun ajaran aktif per sekolah" tidak bisa dilanggar via create biasa.
     */
    public function store(AcademicYearRequest $request, School $school)
    {
        $this->authorize('create', [AcademicYear::class, $school]);

        $academicYear = $school->academicYears()->create(
            $request->validated() + ['status' => 'inactive']
        );

        return $this->success($academicYear, 'Tahun ajaran berhasil dibuat.', 201);
    }

    public function show(Request $request, School $school, AcademicYear $academicYear)
    {
        $this->ensureBelongsToSchool($school, $academicYear);
        $this->authorize('view', $academicYear);

        return $this->success($academicYear);
    }

    public function update(AcademicYearRequest $request, School $school, AcademicYear $academicYear)
    {
        $this->ensureBelongsToSchool($school, $academicYear);
        $this->authorize('update', $academicYear);

        $data = $request->validated();
        unset($data['status']); // status hanya berubah lewat activate().

        $academicYear->update($data);

        return $this->success($academicYear, 'Tahun ajaran berhasil diperbarui.');
    }

    public function activate(Request $request, School $school, AcademicYear $academicYear)
    {
        $this->ensureBelongsToSchool($school, $academicYear);
        $this->authorize('activate', $academicYear);

        $academicYear = $this->academicYearService->setActive($academicYear);

        return $this->success($academicYear, 'Tahun ajaran diaktifkan.');
    }

    public function destroy(Request $request, School $school, AcademicYear $academicYear)
    {
        $this->ensureBelongsToSchool($school, $academicYear);
        $this->authorize('delete', $academicYear);

        abort_if($academicYear->status === 'active', 422, 'Tahun ajaran aktif tidak dapat dihapus.');

        $academicYear->delete();

        return $this->success(null, 'Tahun ajaran berhasil dihapus.');
    }

    /**
     * Dicek SEBELUM authorize() supaya akademik tahun dari sekolah lain balikin 404,
     * bukan 403 — mencegah kebocoran informasi "resource ini ada tapi bukan milikmu".
     */
    private function ensureBelongsToSchool(School $school, AcademicYear $academicYear): void
    {
        abort_if($academicYear->school_id !== $school->id, 404);
    }
}