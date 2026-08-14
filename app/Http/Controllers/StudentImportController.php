<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentImportUploadRequest;
use App\Models\ImportBatch;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentImportController extends Controller
{
    public function __construct(private StudentImportService $service)
    {
    }

    /**
     * POST /api/students/import/preview
     * Baca file, validasi tiap baris, TIDAK menyimpan ke students/enrollments.
     */
    public function preview(StudentImportUploadRequest $request): JsonResponse
    {
        $batch = $this->service->preview(
            $request->file('file'),
            $request->integer('academic_year_id'),
            $request->user(),
        );

        return response()->json([
            'token' => $batch->token,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'invalid_rows' => $batch->invalid_rows,
            'rows' => $batch->rows_payload,
        ]);
    }

    /**
     * POST /api/students/import/commit
     * Body: { "token": "..." }
     * Insert baris valid secara transactional. Baris invalid diabaikan
     * (tidak pernah masuk DB) — user harus perbaiki file dan preview ulang
     * kalau mau baris itu ikut masuk.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'uuid']]);

        $batch = ImportBatch::where('token', $request->string('token'))->firstOrFail();

        if ($batch->uploaded_by !== $request->user()->id) {
            abort(403, 'Batch ini bukan milik Anda.');
        }

        try {
            $created = $this->service->commit($batch);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => count($created).' siswa berhasil diimport.',
            'created' => $created,
        ]);
    }
}
