<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SelfAccountDeletionService
{
    public function delete(User $account, array $verification): void
    {
        $photo = DB::transaction(function () use ($account, $verification): ?string {
            $user = User::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($verification['verification'] === 'password' && ! Hash::check($verification['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Kata sandi tidak sesuai.'])->errorBag('accountDeletion');
            }
            if (CashierShift::query()->where('user_id', $user->id)->whereNull('closed_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['account' => 'Tutup shift kasir Anda sebelum menghapus akun.'])->errorBag('accountDeletion');
            }
            $photo = $user->profile_photo_path;
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Akun Dihapus',
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(12)).'@accounts.invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'google_id' => null,
                'remember_token' => null,
                'profile_photo_path' => null,
            ])->save();
            $user->delete();

            return $photo;
        });

        // Auth providers exclude the deleted user even when another session driver is used.
        if (config('session.driver') === 'database') {
            $connection = config('session.connection');
            $table = config('session.table', 'sessions');
            if (Schema::connection($connection)->hasTable($table)) {
                DB::connection($connection)->table($table)->where('user_id', $account->id)->delete();
            }
        }
        if ($photo && str_starts_with($photo, 'profile-photos/')) {
            try {
                Storage::disk('public')->delete($photo);
            } catch (\Throwable $error) {
                Log::warning('Deleted account profile photo could not be removed.', ['user_id' => $account->id]);
            }
        }
    }
}
