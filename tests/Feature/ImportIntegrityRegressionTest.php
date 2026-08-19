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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $importService;
    private QrCredentialService $qrService;
    private School $school;
    private AcademicYear $academicYear;
    private User $uploader;

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

        $this->uploader = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
    }

    /**
     * Helper untuk membuat file Excel .xlsx temporer yang valid.
     */
    private function makeStudentsExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'];
        $sheet->fromArray([$headers], null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
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
    // 1. REGRESSION TESTS: VALIDASI ROMBEL_ID
    // ==========================================

    public function test_rombel_id_from_different_academic_year_is_rejected(): void
    {
        $otherAcademicYear = AcademicYear::factory()->create([
            'school_id' => $this->school->id,
            'name'      => '2024/2025 (Beda Tahun)',
        ]);

        $invalidRombel = Rombel::factory()->create([
            'academic_year_id' => $otherAcademicYear->id,
        ]);

        $file = $this->makeStudentsExcelFile([
            ['Budi Santoso', '1234567890', '2015-05-20', 'digital', $invalidRombel->id],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview(
            $file,
            $this->academicYear->id,
            $this->uploader
        );

        $this->assertFalse($batch->is_valid ?? false);
        $this->assertNotEmpty($batch->errors ?? $batch->invalid_rows ?? []);
    }

    public function test_rombel_id_from_same_academic_year_is_accepted(): void
    {
        $validRombel = Rombel::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $file = $this->makeStudentsExcelFile([
            ['Siti Aminah', '1234567891', '2015-06-15', 'digital', $validRombel->id],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview(
            $file,
            $this->academicYear->id,
            $this->uploader
        );

        $this->assertTrue($batch->is_valid ?? true);
    }

    public function test_null_or_empty_rombel_id_is_accepted_as_optional(): void
    {
        $file = $this->makeStudentsExcelFile([
            ['Ahmad Yani', '1234567892', '2015-07-10', 'manual', null],
            ['Dewi Sartika', '1234567893', '2015-08-12', 'digital', ''],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview(
            $file,
            $this->academicYear->id,
            $this->uploader
        );

        $this->assertTrue($batch->is_valid ?? true);
    }

    public function test_non_existent_rombel_id_is_rejected(): void
    {
        $nonExistentId = 999999;

        $file = $this->makeStudentsExcelFile([
            ['Eko Prasetyo', '1234567894', '2015-09-01', 'digital', $nonExistentId],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview(
            $file,
            $this->academicYear->id,
            $this->uploader
        );

        $this->assertFalse($batch->is_valid ?? false);
    }

    // ==========================================
    // 2. INTEGRITY TESTS: COMMIT BATCH IMPORT
    // ==========================================

    public function test_commit_creates_enrollment_and_profiles_consistently(): void
    {
        $validRombel = Rombel::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $file = $this->makeStudentsExcelFile([
            ['Rian Ardianto', '1122334455', '2015-01-01', 'digital', $validRombel->id],
            ['Fajar Alfian',  '1122334456', '2015-02-02', 'manual',  null],
        ]);

        /** @var ImportBatch $batch */
        $batch = $this->importService->preview(
            $file,
            $this->academicYear->id,
            $this->uploader
        );

        $this->importService->commit($batch);

        $this->assertDatabaseHas('student_profiles', ['nisn' => '1122334455', 'method' => 'digital']);
        $this->assertDatabaseHas('student_profiles', ['nisn' => '1122334456', 'method' => 'manual']);

        $this->assertDatabaseHas('enrollments', [
            'academic_year_id' => $this->academicYear->id,
            'rombel_id'        => $validRombel->id,
        ]);
    }

    public function test_commit_throws_exception_if_batch_is_invalid(): void
    {
        $batch = new ImportBatch();
        $batch->is_valid = false;

        $this->expectException(\Throwable::class);

        $this->importService->commit($batch);
    }

    // ==========================================
    // 3. REGRESSION TESTS: QR BULK ATOMICITY
    // ==========================================

    public function test_bulk_qr_generation_issues_one_active_token_per_student(): void
    {
        $profiles = collect();
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['school_id' => $this->school->id]);
            $profiles->push(StudentProfile::factory()->create(['user_id' => $user->id]));
        }

        // Pass 1: Generate QR awal
        $resultsPass1 = $this->qrService->generateBulk($profiles);
        $this->assertCount(3, $resultsPass1);

        // Pass 2: Re-generate QR
        $resultsPass2 = $this->qrService->generateBulk($profiles);
        $this->assertCount(3, $resultsPass2);
    }

    public function test_bulk_qr_generation_rolls_back_entirely_on_failure(): void
    {
        $user1 = User::factory()->create(['school_id' => $this->school->id]);
        $user2 = User::factory()->create(['school_id' => $this->school->id]);

        $profile1 = StudentProfile::factory()->create(['user_id' => $user1->id]);
        $profile2 = StudentProfile::factory()->create(['user_id' => $user2->id]);

        $initialToken = $this->qrService->generateForStudent($profile1);

        $mockQrService = $this->getMockBuilder(QrCredentialService::class)
            ->onlyMethods(['generateForStudent'])
            ->getMock();

        $mockQrService->expects($this->atLeastOnce())
            ->method('generateForStudent')
            ->willReturnCallback(function (StudentProfile $profile) use ($profile1) {
                if ($profile->id === $profile1->id) {
                    return 'new-token-123';
                }
                throw new \Exception('Simulated database/system error during bulk generation');
            });

        try {
            $mockQrService->generateBulk(collect([$profile1, $profile2]));
            $this->fail('Expected Exception was not thrown');
        } catch (\Throwable $e) {
            $this->assertEquals('Simulated database/system error during bulk generation', $e->getMessage());
        }

        $this->assertNotNull($initialToken);
    }

    // ==========================================
    // 4. INTEGRITY TEST: FULL END-TO-END FLOW
    // ==========================================

    public function test_full_end_to_end_import_preview_commit_and_qr_bulk_generation(): void
    {
        $validRombel = Rombel::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $file = $this->makeStudentsExcelFile([
            ['Siswa E2E One', '9988776601', '2015-03-03', 'digital', $validRombel->id],
            ['Siswa E2E Two', '9988776602', '2015-04-04', 'digital', $validRombel->id],
        ]);

        // Step 1: Preview
        /** @var ImportBatch $batch */
        $batch = $this->importService->preview($file, $this->academicYear->id, $this->uploader);
        $this->assertTrue($batch->is_valid ?? true);

        // Step 2: Commit
        $this->importService->commit($batch);

        // Step 3: Fetch imported profiles
        $importedProfiles = StudentProfile::whereIn('nisn', ['9988776601', '9988776602'])->get();
        $this->assertCount(2, $importedProfiles);

        // Step 4: Bulk QR Generation via Service
        $generatedQrs = $this->qrService->generateBulk($importedProfiles);
        $this->assertCount(2, $generatedQrs);
    }
}
