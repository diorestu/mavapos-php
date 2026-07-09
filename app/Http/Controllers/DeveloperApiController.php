<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperApiController extends Controller
{
    public function getTokens(): JsonResponse
    {
        $tokens = auth()->user()->tokens()
            ->latest()
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at->translatedFormat('d M Y H:i'),
                'last_used_at' => $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Belum pernah',
            ]);

        return response()->json($tokens);
    }

    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $tokenResult = auth()->user()->createToken($validated['name']);

        return response()->json([
            'token' => $tokenResult->plainTextToken,
            'name' => $validated['name'],
        ]);
    }

    public function revokeToken($id): JsonResponse
    {
        auth()->user()->tokens()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }
}
