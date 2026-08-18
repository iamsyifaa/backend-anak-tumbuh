<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\StudentQrAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class StudentQrLoginController extends Controller
{
    public function __construct(private StudentQrAuthService $qrAuthService) {}

    public function __invoke(Request $request)
    {
        $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->qrAuthService->loginWithQr($request->input('qr_token'));
        } catch (AuthenticationException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json([
            'user' => ['id' => $result['user']->id],
            'student_profile' => [
                'id' => $result['student_profile']->id,
                'full_name' => $result['student_profile']->full_name,
                'nisn' => $result['student_profile']->nisn,
            ],
            'token' => $result['token'],
        ]);
    }
}
