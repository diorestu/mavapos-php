<?php

namespace App\Http\Controllers;

use App\Services\SelfAccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class ProfileController extends Controller
{
    public function destroy(Request $request, SelfAccountDeletionService $service): RedirectResponse
    {
        $data = $request->validateWithBag('accountDeletion', [
            'verification' => ['required', Rule::in(['password', 'google'])],
            'password' => [Rule::requiredIf($request->input('verification') === 'password'), 'nullable', 'string'],
            'confirmation' => ['required', Rule::in(['HAPUS AKUN'])],
            'understand' => ['accepted'],
        ], [
            'confirmation.in' => 'Ketik HAPUS AKUN untuk mengonfirmasi.',
            'understand.accepted' => 'Konfirmasikan dampak penghapusan akun terlebih dahulu.',
            'password.required' => 'Masukkan kata sandi Anda saat ini.',
        ]);
        if ($data['verification'] === 'google' && ! $this->googleDeletionVerified($request)) {
            throw ValidationException::withMessages(['verification' => 'Verifikasi ulang akun Google yang sama. Verifikasi berlaku selama 5 menit.'])->errorBag('accountDeletion');
        }
        $service->delete($request->user(), $data);
        $request->user()->setRememberToken(null);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('signin')->with('status', 'Akun Anda berhasil dihapus.');
    }

    public function verifyGoogleDeletion(Request $request)
    {
        abort_unless($request->user()->google_id, 422, 'Akun ini tidak terhubung dengan Google.');
        $request->session()->forget('account_deletion.google');
        $request->session()->put('account_deletion.oauth', ['user_id' => $request->user()->id, 'started_at' => now()->timestamp]);

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function completeGoogleDeletion(Request $request, $googleUser): RedirectResponse
    {
        $flow = $request->session()->pull('account_deletion.oauth');
        $request->session()->forget('account_deletion.google');
        $user = $request->user();
        if (! $user) {
            return redirect()->route('signin');
        }
        $valid = is_array($flow) && ($flow['user_id'] ?? null) === $user->id
            && is_numeric($flow['started_at'] ?? null) && $flow['started_at'] >= now()->subMinutes(5)->timestamp
            && $flow['started_at'] <= now()->timestamp && $user->google_id && $googleUser
            && hash_equals((string) $user->google_id, (string) $googleUser->getId());
        if (! $valid) {
            return redirect()->route('profile')->withErrors(['verification' => 'Verifikasi Google gagal atau akun Google berbeda. Silakan coba lagi.'], 'accountDeletion');
        }
        $request->session()->put('account_deletion.google', ['user_id' => $user->id, 'verified_at' => now()->timestamp]);

        return redirect()->route('profile')->with('status', 'Akun Google terverifikasi. Anda dapat melanjutkan penghapusan akun dalam 5 menit.');
    }

    public function googleDeletionVerified(Request $request): bool
    {
        $proof = $request->session()->get('account_deletion.google');

        return $request->user()?->google_id && is_array($proof) && ($proof['user_id'] ?? null) === $request->user()->id
            && is_numeric($proof['verified_at'] ?? null) && $proof['verified_at'] >= now()->subMinutes(5)->timestamp
            && $proof['verified_at'] <= now()->timestamp;
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate(['profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = $request->user();
        $path = $validated['profile_photo']->store('profile-photos', 'public');

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => $path]);

        return redirect()->route('profile')->with('success', 'Foto profil berhasil diperbarui.');
    }
}
