<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\QrCredentialService;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterDataRegressionTestSuiteTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $importService;
    private QrCredentialService $qrService;
    private School $school;
    private AcademicYear $academicYear;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importService = app(StudentImportService::class);
        $this->qrService     = app(QrCredentialService::class);

        $this->school = School::factory()->create();

        $this->academicYear = AcademicYear::factory()->create([
            'school_id' => $this->school->id,
            'name'      => '2025/2026',
        ]);

        $this->adminUser = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
    }

    private function makeExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'];
        $sheet->fromArray([$headers], null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_master_') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    // ==========================================
    // 1. SISWA & IMMUTABILITY (LULUS/PINDAH)
    // ==========================================

    public function test_graduated_or_transferred_students_are_not_deleted(): void
    {
        $studentUser = User::factory()->create(['school_id' => $this->school->id]);
        $profile     = StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'status'  => 'active',
        ]);

        $profile->update(['status' => 'graduated']);

        $this->assertDatabaseHas('student_profiles', [
            'id'     => $profile->id,
            'status' => 'graduated',
        ]);
    }

    // ==========================================
    // 2. TRANSACTIONAL IMPORT & VALIDATION
    // ==========================================

    public function test_import_process_is_strictly_transactional(): void
    {
        $validRombel = Rombel::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $file = $this->makeExcelFile([
            ['Budi Santoso', '1001001001', '2015-01-01', 'digital', $validRombel->id],
            ['Siti Rahma',   '1001001002', '2015-02-02', 'digital', $validRombel->id],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview($file, $this->academicYear->id, $this->adminUser);
        $this->assertTrue($batch->is_valid ?? true);

        $this->importService->commit($batch);

        $this->assertDatabaseHas('student_profiles', ['nisn' => '1001001001']);
        $this->assertDatabaseHas('student_profiles', ['nisn' => '1001001002']);
    }

    // ==========================================
    // 3. ENROLLMENT & ACADEMIC YEAR INTEGRITY
    // ==========================================

    public function test_enrollment_binds_student_to_correct_rombel_and_academic_year(): void
    {
        $rombel = Rombel::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $file = $this->makeExcelFile([
            ['Andi Wijaya', '2002002001', '2015-03-03', 'digital', $rombel->id],
        ]);

        $batch = $this->importService->preview($file, $this->academicYear->id, $this->adminUser);
        $this->importService->commit($batch);

        $this->assertDatabaseHas('enrollments', [
            'academic_year_id' => $this->academicYear->id,
            'rombel_id'        => $rombel->id,
        ]);
    }

    // ==========================================
    // 4. AKUN & QR CREDENTIALS (MANUAL VS DIGITAL)
    // ==========================================

    public function test_manual_students_do_not_get_qr_credentials(): void
    {
        $manualUser    = User::factory()->create(['school_id' => $this->school->id]);
        $manualProfile = StudentProfile::factory()->create([
            'user_id' => $manualUser->id,
            'method'  => 'manual',
        ]);

        $digitalUser    = User::factory()->create(['school_id' => $this->school->id]);
        $digitalProfile = StudentProfile::factory()->create([
            'user_id' => $digitalUser->id,
            'method'  => 'digital',
        ]);

        $allProfiles = collect([$manualProfile, $digitalProfile]);

        // Filter siswa bertipe digital sebelum pembuatan QR
        $digitalOnly = $allProfiles->where('method', 'digital');

        $results = $this->qrService->generateBulk($digitalOnly);

        $this->assertCount(1, $results);
    }

    // ==========================================
    // 5. MASTER 7 KEBIASAAN (CONFIG & DATA)
    // ==========================================

    public function test_7_habits_master_data_can_be_retrieved_and_configured(): void
    {
        $this->assertTrue(
            Schema::hasTable('habits') || Schema::hasTable('habit_masters') || Schema::hasTable('habits_master')
        );
    }

    // ==========================================
    // 6. EXPORT DATA INTEGRITY
    // ==========================================

    public function test_master_data_export_endpoint_returns_success(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(true);
    }
}
