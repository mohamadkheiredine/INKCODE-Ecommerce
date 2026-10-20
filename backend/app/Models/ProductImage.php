<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One picture of a Product, stored on the public disk so the browser loads it directly.
 * Position 0 is the main image. Image changes are recorded on the Product.
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'position'])]
class ProductImage extends Model
{
    public const string DISK = 'public';

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }
}
