<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShiftSummaryService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class AuthController extends Controller
{
    public function showSignIn(): View
    {
        return view('pages.auth.signin', ['title' => 'Masuk']);
    }

    public function signIn(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Email atau kata sandi tidak sesuai.',
                    'errors' => [
                        'email' => ['Email atau kata sandi tidak sesuai.']
                    ]
                ], 422);
            }

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Email atau kata sandi tidak sesuai.',
                ]);
        }

        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Berhasil masuk.',
                'user' => auth()->user(),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna Google',
                'email' => $googleUser->getEmail(),
                'email_verified_at' => now(),
                'password' => Str::random(32),
                'google_id' => $googleUser->getId(),
                'role' => 'owner',
                'trial_ends_at' => now()->addDays(14),
            ]);
        }

        Auth::login($user, true);

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showSignUp(): View
    {
        return view('pages.auth.signup', ['title' => 'Daftar']);
    }

    public function signUp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'terms' => ['accepted'],
        ], [
            'first_name.required' => 'Nama depan wajib diisi.',
            'last_name.required' => 'Nama belakang wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal harus :min karakter.',
            'terms.accepted' => 'Anda harus menyetujui syarat dan kebijakan privasi.',
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'owner',
            'trial_ends_at' => now()->addDays(14),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Registrasi berhasil.',
                'user' => $user,
            ], 201);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $recap = null;

        if ($user?->hasRole('kasir')) {
            $branchId = app(BranchContext::class)->activeId();
            $shift = DB::transaction(function () use ($user, $branchId): ?CashierShift {
                $shift = CashierShift::query()
                    ->where('user_id', $user->id)
                    ->where('branch_id', $branchId)
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $shift) {
                    return null;
                }

                $shift->update([
                    'closed_at' => now(),
                    'closing_note' => $shift->closing_note ?: 'Sesi personal berakhir lewat logout.',
                ]);

                return app(CashierShiftSummaryService::class)->refresh($shift)->load(['user', 'branch']);
            });

            $recap = $shift ? app(CashierShiftSummaryService::class)->recap($shift) : null;
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Berhasil keluar.',
                'recap' => $recap,
            ]);
        }

        return redirect()->route('signin');
    }
}
