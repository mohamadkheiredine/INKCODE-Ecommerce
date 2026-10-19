<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Who makes a Product. Products can be filtered and reported by Brand.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Brand extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * Logos live on the public disk, so the browser loads them directly.
     */
    public const string LOGO_DISK = 'public';

    /**
     * The table's default, so a new Brand (and its audit entry) shows "no logo" before
     * being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'logo_path' => null,
    ];

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk(self::LOGO_DISK)->url($this->logo_path);
    }
}
