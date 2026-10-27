<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Конвертація завантажених картинок у WebP із зменшенням до максимальної ширини (ТЗ: WebP, CDN).
 * Анімовані GIF (банери) зберігаються як є, щоб не втратити анімацію.
 */
final class ImageOptimizer
{
    public const MAX_WIDTH = [
        'logo' => 512, 'banner' => 1200, 'screenshot' => 1920,
        // Зображення головної з адмінки
        'hero' => 2400, 'cover' => 1600, 'icon' => 256, 'og' => 1200, 'site_logo' => 600,
    ];

    /** Більші фото GD розпаковує в сотні МБ пам’яті — такі зберігаємо без обробки */
    private const MAX_PIXELS = 40_000_000;

    public static function store(UploadedFile $file, string $directory, string $type): string
    {
        $maxWidth = self::MAX_WIDTH[$type] ?? 1920;
        [$w, $h] = @getimagesize($file->getRealPath()) ?: [0, 0];

        if (! function_exists('imagewebp') || self::isAnimatedGif($file) || $w * $h > self::MAX_PIXELS) {
            return $file->store($directory, 'public');
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            return $file->store($directory, 'public');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth) {
            $scaled = imagescale($image, $maxWidth, (int) round($height * $maxWidth / $width), IMG_BICUBIC);
            imagedestroy($image);
            $image = $scaled;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        $path = trim($directory, '/').'/'.Str::random(32).'.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    private static function isAnimatedGif(UploadedFile $file): bool
    {
        if ($file->getMimeType() !== 'image/gif') {
            return false;
        }

        // Більше одного кадру = анімація
        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', (string) file_get_contents($file->getRealPath())) > 1;
    }
}
