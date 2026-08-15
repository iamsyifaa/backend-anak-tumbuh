<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    // GET /api/certificate-templates
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        return response()->json(['status' => 'success', 'data' => CertificateTemplate::all()]);
    }

    // POST /api/certificate-templates
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CertificateTemplate::class);

        $validated = $request->validate([
            'code' => 'required|string|unique:certificate_templates,code',
            'name' => 'required|string',
            'background_image_path' => 'nullable|string',
            'layout_config' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        $template = CertificateTemplate::create($validated);

        return response()->json(['status' => 'success', 'data' => $template], 201);
    }

    // PUT /api/certificate-templates/{certificateTemplate}
    public function update(Request $request, CertificateTemplate $certificateTemplate): JsonResponse
    {
        $this->authorize('update', CertificateTemplate::class);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:certificate_templates,code,'.$certificateTemplate->id,
            'name' => 'sometimes|required|string',
            'background_image_path' => 'nullable|string',
            'layout_config' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        $certificateTemplate->update($validated);

        return response()->json(['status' => 'success', 'data' => $certificateTemplate]);
    }

    // DELETE /api/certificate-templates/{certificateTemplate}
    public function destroy(CertificateTemplate $certificateTemplate): JsonResponse
    {
        $this->authorize('delete', CertificateTemplate::class);

        $certificateTemplate->delete();

        return response()->json(['status' => 'success', 'message' => 'Template berhasil dihapus']);
    }
}
