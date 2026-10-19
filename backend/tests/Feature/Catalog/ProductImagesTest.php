<?php

use App\Enums\Role;
use App\Http\Requests\Catalog\StoreProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->product = Product::factory()->create();
});

/**
 * @param  list<string>  $paths
 */
function givenImages(Product $product, array $paths): void
{
    foreach ($paths as $position => $path) {
        Storage::disk('public')->put($path, 'image');
        $product->images()->create(['path' => $path, 'position' => $position]);
    }
}

describe('Product images', function () {
    it('adds several images after the existing ones; the first is the main image', function () {
        givenImages($this->product, ['products/old.png']);

        $response = $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$this->product->id}/images", ['images' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.webp'),
            ]])
            ->assertOk()
            ->assertJsonCount(3, 'data.images');

        $paths = $this->product->images()->pluck('path');
        expect($paths->first())->toBe('products/old.png');
        $paths->each(fn (string $path) => Storage::disk('public')->assertExists($path));
        $response->assertJsonPath('data.images.0.url', Storage::disk('public')->url('products/old.png'));

        expect(auditTrailOf('product', $this->product->id)->sole()->only('before', 'after'))->toBe([
            'before' => ['images' => ['products/old.png']],
            'after' => ['images' => $paths->all()],
        ]);
    });

    it('accepts only PNG, JPEG or WebP images up to 2 MB', function (UploadedFile $file) {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$this->product->id}/images", ['images' => [$file]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');

        expect($this->product->images()->count())->toBe(0);
    })->with([
        'an SVG' => fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        'a PDF' => fn () => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'over 2 MB' => fn () => UploadedFile::fake()->image('big.png')->size(2049),
    ]);

    it('refuses more images than a Product may have', function () {
        givenImages($this->product, array_map(fn (int $n): string => "products/$n.png", range(1, StoreProductImagesRequest::MAX_IMAGES - 1)));

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$this->product->id}/images", ['images' => [
                UploadedFile::fake()->image('a.png'),
                UploadedFile::fake()->image('b.png'),
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images' => 'A Product can have at most 10 images.']);
    });

    it('reorders the images; the first becomes the main image', function () {
        givenImages($this->product, ['products/a.png', 'products/b.png', 'products/c.png']);
        [$a, $b, $c] = $this->product->images->modelKeys();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/images/order", ['image_ids' => [$c, $a, $b]])
            ->assertOk()
            ->assertJsonPath('data.images.*.id', [$c, $a, $b]);

        $this->getJson('/api/v1/products')->assertJsonPath('data.0.main_image_url', Storage::disk('public')->url('products/c.png'));
    });

    it('refuses an order that leaves out or adds images', function () {
        givenImages($this->product, ['products/a.png', 'products/b.png']);
        $other = Product::factory()->create();
        givenImages($other, ['products/z.png']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/images/order", ['image_ids' => [
                $this->product->images->first()?->id, $other->images->first()?->id,
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_ids' => "List each of the Product's images exactly once."]);
    });

    it('removes an image and its file', function () {
        givenImages($this->product, ['products/a.png', 'products/b.png']);
        $first = $this->product->images->first();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/products/{$this->product->id}/images/{$first?->id}")
            ->assertOk()
            ->assertJsonPath('data.images.*.url', [Storage::disk('public')->url('products/b.png')]);

        Storage::disk('public')->assertMissing('products/a.png');
        expect(ProductImage::query()->count())->toBe(1);
    });

    it('removes image files with their Product', function () {
        givenImages($this->product, ['products/a.png']);

        $this->actingAs(userWithRole(Role::Operations))->deleteJson("/api/v1/products/{$this->product->id}")->assertNoContent();

        Storage::disk('public')->assertMissing('products/a.png');
    });

    it('refuses Roles that may not change the catalog', function () {
        $this->actingAs(userWithRole(Role::Viewer))
            ->postJson("/api/v1/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('a.png')]])
            ->assertForbidden();
    });
});
