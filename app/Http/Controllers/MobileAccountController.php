<?php

namespace App\Http\Controllers;

use App\Services\GoogleDeletionTokenVerifier;
use App\Services\SelfAccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileAccountController extends Controller
{
    public function __invoke(Request $request, SelfAccountDeletionService $service, GoogleDeletionTokenVerifier $google): JsonResponse
    {
        $mode = $request->input('verification', 'password');
        $data = $request->validate([
            'verification' => ['sometimes', Rule::in(['password', 'google'])],
            'password' => [Rule::requiredIf($mode === 'password'), 'nullable', 'string'],
            'google_id_token' => [Rule::requiredIf($mode === 'google'), 'nullable', 'string', 'max:16384'],
            'confirmation' => ['required', Rule::in(['HAPUS AKUN'])],
            'understand' => ['accepted'],
        ]);
        $data['verification'] = $mode;
        if ($mode === 'google') {
            $google->verify($data['google_id_token'], $request->user());
        }
        $service->delete($request->user(), $data);

        return response()->json(['message' => 'Akun Anda berhasil dihapus.', 'deleted' => true]);
    }
}
