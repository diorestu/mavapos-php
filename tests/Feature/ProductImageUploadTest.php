<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('owner dapat mengunggah gambar produk yang tersedia untuk POS', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);

    $response = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Es Kopi Foto',
        'sku' => 'IMG-001',
        'sellPrice' => 18000,
        'stock' => 4,
        'stockMode' => 'inventory',
        'image' => UploadedFile::fake()->image('es-kopi.jpg', 800, 600),
    ]);

    $response->assertCreated()
        ->assertJsonPath('product.imageUrl', fn (?string $url): bool => str_contains((string) $url, '/storage/products/'));

    $path = $response->json('product.imagePath');
    Storage::disk('public')->assertExists($path);
});
