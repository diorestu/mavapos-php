<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('user dapat mengganti foto profil dan header menampilkannya', function () {
    Storage::fake('public');
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs($user)->post(route('profile.photo.update'), ['profile_photo' => UploadedFile::fake()->image('avatar.jpg')])->assertRedirect(route('profile'));

    $user->refresh();
    expect($user->profile_photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->profile_photo_path);
    $this->actingAs($user)->get(route('dashboard'))->assertSee(Storage::disk('public')->url($user->profile_photo_path), false);
});

test('header memakai inisial saat user belum memiliki foto profil', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs($user)->get(route('dashboard'))->assertSee('BS');
});
