<?php

namespace App\Filament\Support;

use App\Support\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Поле завантаження картинки в адмінці: до 10 МБ, після завантаження — WebP потрібної ширини (ImageOptimizer).
 */
final class ImageField
{
    public const MAX_KB = 10240;

    public static function make(string $name, string $directory, string $type): FileUpload
    {
        return FileUpload::make($name)
            ->image()->imageEditor()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->maxSize(self::MAX_KB)
            ->disk('public')->directory($directory)->visibility('public')
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => ImageOptimizer::store($file, $directory, $type));
    }
}
