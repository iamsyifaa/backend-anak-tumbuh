<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Standardized response envelope untuk seluruh API ANAKTUMBUH.ID.
 * Dipakai oleh semua controller agar format sukses/error konsisten
 * (acceptance criteria AUTH-001: "standardized auth errors" — dibuat generik
 * agar dipakai juga oleh domain lain, bukan hanya auth).
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
