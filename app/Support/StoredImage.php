<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keeps an uploaded image on the public disk in step with the record that
 * points at it: a new upload replaces the old file, and removing the image
 * or deleting the record clears it, so no orphaned files are left behind.
 */
class StoredImage
{
    public const string DISK = 'public';

    /**
     * Resolve the path to save for an image field, storing or deleting files
     * as needed. An upload wins over a removal request.
     */
    public static function sync(?string $current, ?UploadedFile $upload, bool $remove, string $directory): ?string
    {
        if ($upload !== null) {
            $path = $upload->store($directory, self::DISK)
                ?: throw new RuntimeException('The image could not be stored.');

            self::delete($current);

            return $path;
        }

        if ($remove) {
            self::delete($current);

            return null;
        }

        return $current;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk(self::DISK)->url($path) : null;
    }
}
