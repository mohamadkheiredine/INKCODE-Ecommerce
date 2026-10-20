<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use App\Support\LogoFile;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        return LogoFile::url($this->logo_path);
    }
}
