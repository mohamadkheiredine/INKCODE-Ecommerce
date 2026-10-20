<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Throwable;

/**
 * A logo image kept on the public disk, so the browser loads it directly: a Brand's
 * logo, the store's logo.
 */
final class LogoFile
{
    public const string DISK = 'public';

    public const int MAX_KILOBYTES = 1024;

    /**
     * A PNG, JPEG or WebP image of at most 1 MB. SVG is refused, because an SVG served
     * from our own origin can carry scripts.
     *
     * @return list<File|string>
     */
    public static function rules(): array
    {
        return ['required', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(self::MAX_KILOBYTES), 'image'];
    }

    public static function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk(self::DISK)->url($path);
    }

    /**
     * Stores the new logo (or none) under a generated name and hands its path to `$save`.
     * The old file is removed only once `$save` succeeds, so a failure never leaves a
     * record pointing at a missing file; on failure the new file is removed instead.
     *
     * @param  Closure(string|null): void  $save  saves the new path on the record
     */
    public static function replace(?UploadedFile $logo, ?string $oldPath, string $directory, Closure $save): void
    {
        $disk = Storage::disk(self::DISK);
        $newPath = $logo?->store($directory, self::DISK) ?: null;

        try {
            $save($newPath);
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }
    }
}
