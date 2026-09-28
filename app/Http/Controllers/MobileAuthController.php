<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()->where('email', $data['email'])->whereIn('role', ['owner', 'admin', 'kasir'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Email atau password tidak sesuai.');

        $token = $user->createToken('mobile-pos', ['mobile:pos'])->plainTextToken;
        auth()->setUser($user);
        $branch = $user->branch ?: app(\App\Support\BranchContext::class)->active();

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role', 'branch_id']),
            'branch' => $branch ? $branch->only(['id', 'name', 'code']) : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $branch = $request->user()->branch ?: app(\App\Support\BranchContext::class)->active();
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role', 'branch_id']),
            'branch' => $branch ? $branch->only(['id', 'name', 'code']) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token mobile dicabut.']);
    }
}
