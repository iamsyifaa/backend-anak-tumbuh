<?php

namespace App\Services;

use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Models\Rombel;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportService
{
    private const REQUIRED_COLUMNS = ['full_name', 'nisn', 'birth_date', 'method'];

    public function preview(UploadedFile $file, int $academicYearId, User $uploader): ImportBatch
    {
        $import = new StudentsImport;
        Excel::import($import, $file);

        $rows = $import->rows ?? collect();

        $academicYear = AcademicYear::findOrFail($academicYearId);

        $validated = $this->validateRows($rows, $academicYear->school_id);

        return ImportBatch::create([
            'token' => (string) Str::uuid(),
            'uploaded_by' => $uploader->id,
            'academic_year_id' => $academicYearId,
            'original_filename' => $file->getClientOriginalName(),
            'rows_payload' => $validated,
            'total_rows' => count($validated),
            'valid_rows' => collect($validated)->where('is_valid', true)->count(),
            'invalid_rows' => collect($validated)->where('is_valid', false)->count(),
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);
    }

    /**
     * @param  int  $schoolId  Sekolah pemilik academic year yang dipakai untuk import ini.
     *                         Dipakai untuk menolak rombel_id yang bukan milik sekolah ini
     *                         (mencegah siswa Sekolah A ke-enroll ke rombel Sekolah B).
     */
    private function validateRows(Collection $rows, int $schoolId): array
    {
        $seenNisn = [];
        $results = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];

            $fullName = trim((string) ($row['full_name'] ?? ''));
            $nisn = trim((string) ($row['nisn'] ?? ''));
            $birthDateRaw = trim((string) ($row['birth_date'] ?? ''));
            $method = strtolower(trim((string) ($row['method'] ?? '')));
            $rombelId = $row['rombel_id'] ?? null;

            if ($fullName === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
            }
            if ($nisn === '') {
                $errors[] = 'NISN wajib diisi.';
            }
            if ($birthDateRaw === '') {
                $errors[] = 'Tanggal lahir wajib diisi.';
            }

            $birthDate = null;
            if ($birthDateRaw !== '') {
                try {
                    $birthDate = Carbon::parse($birthDateRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = "Format tanggal lahir tidak valid: '{$birthDateRaw}' (gunakan YYYY-MM-DD).";
                }
            }

            if (! in_array($method, [StudentProfile::METHOD_DIGITAL, StudentProfile::METHOD_MANUAL], true)) {
                $errors[] = "Method harus 'digital' atau 'manual', ditemukan: '{$method}'.";
            }

            if ($nisn !== '') {
                if (isset($seenNisn[$nisn])) {
                    $errors[] = "NISN duplikat dengan baris {$seenNisn[$nisn]} di file ini.";
                } else {
                    $seenNisn[$nisn] = $rowNumber;
                }
            }

            if ($nisn !== '' && StudentProfile::where('nisn', $nisn)->exists()) {
                $errors[] = "NISN '{$nisn}' sudah terdaftar di sistem.";
            }

            // Cegah kebocoran data lintas sekolah: rombel_id yang diisi di file
            // HARUS milik sekolah yang sama dengan academic year tujuan import.
            if ($rombelId !== null && $rombelId !== '') {
                $rombelBelongsToSchool = Rombel::where('id', $rombelId)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (! $rombelBelongsToSchool) {
                    $errors[] = "Rombel ID '{$rombelId}' tidak ditemukan atau bukan milik sekolah ini.";
                }
            }

            $results[] = [
                'row_number' => $rowNumber,
                'full_name' => $fullName,
                'nisn' => $nisn,
                'birth_date' => $birthDate,
                'method' => $method,
                'rombel_id' => $rombelId,
                'is_valid' => empty($errors),
                'errors' => $errors,
            ];
        }

        return $results;
    }

    public function commit(ImportBatch $batch): array
    {
        if (! $batch->isCommittable()) {
            throw new \RuntimeException('Batch ini tidak bisa di-commit (sudah committed, expired, atau tidak ada baris valid).');
        }

        $validRows = collect($batch->rows_payload)->where('is_valid', true);
        $created = [];

        DB::transaction(function () use ($validRows, $batch, &$created) {
            foreach ($validRows as $row) {
                $username = $this->generateUniqueUsername($row['full_name']);

                $user = User::create([
                    'school_id' => $batch->academicYear->school_id,
                    'username' => $username,
                    'password' => Hash::make(Str::random(16)),
                    'role' => User::ROLE_SISWA,
                    'status' => User::STATUS_ACTIVE,
                    'must_change_password' => true,
                ]);

                $profile = StudentProfile::create([
                    'user_id' => $user->id,
                    'full_name' => $row['full_name'],
                    'method' => $row['method'],
                    'status' => StudentProfile::STATUS_ACTIVE,
                    'birth_date' => $row['birth_date'],
                    'nisn' => $row['nisn'],
                ]);

                Enrollment::create([
                    'student_profile_id' => $profile->id,
                    'academic_year_id' => $batch->academic_year_id,
                    'rombel_id' => $row['rombel_id'],
                    'status' => Enrollment::STATUS_ACTIVE,
                    'started_at' => now(),
                ]);

                $created[] = ['username' => $username, 'nisn' => $row['nisn']];
            }

            $batch->update([
                'status' => ImportBatch::STATUS_COMMITTED,
                'committed_at' => now(),
            ]);
        });

        return $created;
    }

    private function generateUniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '');
        $base = substr($base, 0, 20) ?: 'siswa';
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }
}