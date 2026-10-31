<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\File;

/**
 * A branch's letterhead banner as it is printed: with the blank margin
 * designers often leave above and below the artwork cropped away, so the
 * page starts right under the banner instead of under empty paper.
 *
 * The uploaded file is never changed; the cropped copy is cached on disk and
 * rebuilt whenever the upload changes.
 */
final readonly class LetterheadBanner
{
    /**
     * Channel value above which a pixel counts as blank paper.
     */
    private const int BLANK_THRESHOLD = 245;

    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {}

    /**
     * The printable banner for an image on disk, or null when it is not an
     * image that can be read.
     */
    public static function from(string $path): ?self
    {
        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        $original = new self($path, $size[0], $size[1]);

        $cached = storage_path('app/letterheads/'.sha1($path.'|'.filemtime($path).'|'.filesize($path)).'.png');

        if (is_file($cached) && ($cachedSize = @getimagesize($cached)) !== false) {
            return new self($cached, $cachedSize[0], $cachedSize[1]);
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            return $original;
        }

        [$top, $bottom] = self::contentRows($image, $original->width, $original->height);

        if ($top === 0 && $bottom === $original->height - 1) {
            return $original;
        }

        $cropped = imagecrop($image, ['x' => 0, 'y' => $top, 'width' => $original->width, 'height' => $bottom - $top + 1]);

        if (! $cropped instanceof GdImage) {
            return $original;
        }

        File::ensureDirectoryExists(dirname($cached));
        imagesavealpha($cropped, true);
        imagepng($cropped, $cached);

        return new self($cached, $original->width, $bottom - $top + 1);
    }

    /**
     * The height the banner takes up when printed at the given width.
     */
    public function printedHeight(float $printWidth): int
    {
        return (int) ceil($printWidth * $this->height / $this->width);
    }

    /**
     * The first and last rows that carry any artwork. An image with nothing
     * on it is left whole.
     *
     * @return array{int, int}
     */
    private static function contentRows(GdImage $image, int $width, int $height): array
    {
        // Sampling a few hundred pixels per row is plenty to spot artwork.
        $step = max(1, intdiv($width, 400));
        $isBlank = function (int $y) use ($image, $width, $step): bool {
            for ($x = 0; $x < $width; $x += $step) {
                $colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                if ($colour['alpha'] < 120 && min($colour['red'], $colour['green'], $colour['blue']) < self::BLANK_THRESHOLD) {
                    return false;
                }
            }

            return true;
        };

        $top = 0;
        while ($top < $height - 1 && $isBlank($top)) {
            $top++;
        }

        $bottom = $height - 1;
        while ($bottom > $top && $isBlank($bottom)) {
            $bottom--;
        }

        return $top === $height - 1 ? [0, $height - 1] : [$top, $bottom];
    }
}
