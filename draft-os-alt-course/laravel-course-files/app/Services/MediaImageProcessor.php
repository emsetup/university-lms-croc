<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaImageProcessor
{
    /**
     * @return array{full_path:string,thumb_path:string,width:int,height:int,bytes:int,mime:string}
     */
    public function processAndStore(UploadedFile $file, string $uuid, ?int $courseId): array
    {
        $maxBytes = (int) config('media.max_upload_bytes', 10 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('Файл слишком большой (макс. '.round($maxBytes / 1048576, 1).' МБ).');
        }

        $mime = (string) $file->getMimeType();
        $allowed = config('media.allowed_mimes', []);
        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Неподдерживаемый формат изображения.');
        }

        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return $this->processWithGd($file, $uuid, $courseId, $mime);
        }

        return $this->storeOriginal($file, $uuid, $courseId, $mime);
    }

    /**
     * @return array{full_path:string,thumb_path:string,width:int,height:int,bytes:int,mime:string}
     */
    private function processWithGd(UploadedFile $file, string $uuid, ?int $courseId, string $mime): array
    {
        $src = $this->loadImage($file->getPathname(), $mime);
        if ($src === null) {
            return $this->storeOriginal($file, $uuid, $courseId, $mime);
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $src = $this->applyExifOrientation($src, $file->getPathname());
        }

        $src = $this->resizeToMax($src, (int) config('media.max_dimension', 1920));
        $width = imagesx($src);
        $height = imagesy($src);

        $dir = $this->storageDir($courseId, $uuid);
        $disk = Storage::disk('local');
        $disk->makeDirectory($dir);

        $fullRel = $dir.'/full.webp';
        $thumbRel = $dir.'/thumb.webp';

        $quality = (int) config('media.webp_quality', 85);
        $saved = function_exists('imagewebp')
            ? imagewebp($src, $disk->path($fullRel), $quality)
            : imagejpeg($src, $disk->path($dir.'/full.jpg'), $quality);

        if (! $saved) {
            imagedestroy($src);
            throw new RuntimeException('Не удалось сохранить изображение.');
        }

        if (! function_exists('imagewebp')) {
            $fullRel = $dir.'/full.jpg';
            $thumbRel = $dir.'/thumb.jpg';
        }

        $thumb = $this->resizeToMax($src, (int) config('media.thumb_dimension', 320));
        if (function_exists('imagewebp')) {
            imagewebp($thumb, $disk->path($thumbRel), $quality);
        } else {
            imagejpeg($thumb, $disk->path($thumbRel), $quality);
        }
        imagedestroy($thumb);
        imagedestroy($src);

        $outMime = function_exists('imagewebp') ? 'image/webp' : 'image/jpeg';

        return [
            'full_path' => $fullRel,
            'thumb_path' => $thumbRel,
            'width' => $width,
            'height' => $height,
            'bytes' => (int) filesize($disk->path($fullRel)),
            'mime' => $outMime,
        ];
    }

    /**
     * @return array{full_path:string,thumb_path:string,width:int,height:int,bytes:int,mime:string}
     */
    private function storeOriginal(UploadedFile $file, string $uuid, ?int $courseId, string $mime): array
    {
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $dir = $this->storageDir($courseId, $uuid);
        $disk = Storage::disk('local');
        $disk->makeDirectory($dir);

        $fullRel = $dir.'/original.'.$ext;
        $file->storeAs($dir, 'original.'.$ext, 'local');

        $width = 0;
        $height = 0;
        $size = @getimagesize($disk->path($fullRel));
        if (is_array($size)) {
            $width = (int) ($size[0] ?? 0);
            $height = (int) ($size[1] ?? 0);
        }

        return [
            'full_path' => $fullRel,
            'thumb_path' => $fullRel,
            'width' => $width,
            'height' => $height,
            'bytes' => (int) filesize($disk->path($fullRel)),
            'mime' => $mime,
        ];
    }

    private function storageDir(?int $courseId, string $uuid): string
    {
        $sub = $courseId > 0 ? 'course/'.$courseId.'/'.$uuid : 'personal/'.$uuid;

        return 'media/'.$sub;
    }

    /**
     * @return resource|\GdImage|null
     */
    private function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    /**
     * @param  resource|\GdImage  $img
     * @return resource|\GdImage
     */
    private function applyExifOrientation($img, string $path)
    {
        $exif = @exif_read_data($path);
        if (! is_array($exif) || empty($exif['Orientation'])) {
            return $img;
        }

        return match ((int) $exif['Orientation']) {
            3 => imagerotate($img, 180, 0) ?: $img,
            6 => imagerotate($img, -90, 0) ?: $img,
            8 => imagerotate($img, 90, 0) ?: $img,
            default => $img,
        };
    }

    /**
     * @param  resource|\GdImage  $img
     * @return resource|\GdImage
     */
    private function resizeToMax($img, int $maxDim)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0 || max($w, $h) <= $maxDim) {
            return $img;
        }

        if ($w >= $h) {
            $nw = $maxDim;
            $nh = (int) round($h * ($maxDim / $w));
        } else {
            $nh = $maxDim;
            $nw = (int) round($w * ($maxDim / $h));
        }

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $dst;
    }
}
