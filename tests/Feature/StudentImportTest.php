<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUploader(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function makeAcademicYear(): AcademicYear
    {
        $school = School::factory()->create();

        return AcademicYear::factory()->create(['school_id' => $school->id]);
    }

    private function makeRombel(AcademicYear $year): Rombel
    {
        return Rombel::factory()->create(['school_id' => $year->school_id]);
    }

    public function test_preview_does_not_persist_any_student_data(): void
    {
        $uploader = $this->makeUploader();
        $year = $this->makeAcademicYear();
        $rombel = $this->makeRombel($year);

        $file = $this->buildCsv([
            ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'],
            ['Budi Santoso', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
            ['Ani Wulandari', '1234567891', '2015-06-01', 'manual', (string) $rombel->id],
        ]);

        $service = app(StudentImportService::class);
        $batch = $service->preview($file, $year->id, $uploader);

        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('users', 1); // cuma uploader
    }

    public function test_invalid_rows_are_flagged_and_excluded_from_commit(): void
    {
        $uploader = $this->makeUploader();
        $year = $this->makeAcademicYear();
        $rombel = $this->makeRombel($year);

        $file = $this->buildCsv([
            ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'],
            ['Budi Santoso', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
            ['', '1234567891', 'tanggal-salah', 'entah', (string) $rombel->id],
        ]);

        $service = app(StudentImportService::class);
        $batch = $service->preview($file, $year->id, $uploader);

        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);

        $created = $service->commit($batch->fresh());

        $this->assertCount(1, $created);
        $this->assertDatabaseCount('student_profiles', 1);
        $this->assertDatabaseHas('student_profiles', ['nisn' => '1234567890']);
        $this->assertDatabaseMissing('student_profiles', ['nisn' => '1234567891']);
    }

    public function test_duplicate_nisn_within_file_is_rejected(): void
    {
        $uploader = $this->makeUploader();
        $year = $this->makeAcademicYear();
        $rombel = $this->makeRombel($year);

        $file = $this->buildCsv([
            ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'],
            ['Budi Santoso', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
            ['Budi Duplikat', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
        ]);

        $service = app(StudentImportService::class);
        $batch = $service->preview($file, $year->id, $uploader);

        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);
    }

    public function test_duplicate_nisn_against_existing_database_is_rejected(): void
    {
        $uploader = $this->makeUploader();
        $year = $this->makeAcademicYear();
        $rombel = $this->makeRombel($year);

        StudentProfile::factory()->create(['nisn' => '1234567890']);

        $file = $this->buildCsv([
            ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'],
            ['Budi Santoso', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
        ]);

        $service = app(StudentImportService::class);
        $batch = $service->preview($file, $year->id, $uploader);

        $this->assertSame(0, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);
    }

    public function test_commit_is_atomic_and_cannot_be_committed_twice(): void
    {
        $uploader = $this->makeUploader();
        $year = $this->makeAcademicYear();
        $rombel = $this->makeRombel($year);

        $file = $this->buildCsv([
            ['full_name', 'nisn', 'birth_date', 'method', 'rombel_id'],
            ['Budi Santoso', '1234567890', '2015-05-01', 'digital', (string) $rombel->id],
        ]);

        $service = app(StudentImportService::class);
        $batch = $service->preview($file, $year->id, $uploader);
        $service->commit($batch->fresh());

        $this->expectException(\RuntimeException::class);
        $service->commit($batch->fresh());
    }

    private function buildCsv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'students.csv', 'text/csv', null, true);
    }
}