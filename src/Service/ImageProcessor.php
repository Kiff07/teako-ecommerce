<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Image upload processing: validation, resizing (fit/cover) and cached
 * "variant" generation using GD. Original files live in public/media/{productId}/
 * while derived variants (thumb/card/big/square) are written next to them and
 * re-created lazily when missing.
 */
class ImageProcessor
{
    public const MAX_BYTES = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private readonly string $mediaDir,
        private readonly string $tmpDir,
    ) {
    }

    public function isSupported(UploadedFile $file): bool
    {
        if ($file->getSize() > self::MAX_BYTES) {
            return false;
        }

        return isset(self::ALLOWED_MIME[$file->getMimeType()]);
    }

    public function extensionFor(UploadedFile $file): string
    {
        return self::ALLOWED_MIME[$file->getMimeType()] ?? 'jpg';
    }

    /** Product folder: public/media/{productId} */
    public function productDir(int $productId): string
    {
        return $this->mediaDir.\DIRECTORY_SEPARATOR.$productId;
    }

    public function ensureProductDir(int $productId): string
    {
        $dir = $this->productDir($productId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * Store an uploaded file (already validated) under the product folder and
     * return the relative url (e.g. /media/7/xyz.jpg).
     */
    public function storeUpload(UploadedFile $file, int $productId): string
    {
        $dir = $this->ensureProductDir($productId);
        $ext = $this->extensionFor($file);
        $name = $this->uniqueBasename($dir, $ext);
        $file->move($dir, $name.'.'.$ext);

        return sprintf('/media/%d/%s.%s', $productId, $name, $ext);
    }

    private function uniqueBasename(string $dir, string $ext): string
    {
        $base = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        while (file_exists($dir.\DIRECTORY_SEPARATOR.$base.'.'.$ext)) {
            $base = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        }

        return $base;
    }

    /**
     * Produce (or lazily produce) a resized variant of an existing media file.
     *
     * @return array{url: string, width: int, height: int}
     */
    public function variant(string $originalUrl, string $variant): array
    {
        $path = $this->urlToPath($originalUrl);
        $dim = $this->variantDimensions($variant);

        if (null === $path || !is_file($path)) {
            return ['url' => $originalUrl, 'width' => 0, 'height' => 0];
        }

        $info = @getimagesize($path);
        $srcW = $info[0] ?? 0;
        $srcH = $info[1] ?? 0;
        if (0 === $srcW || 0 === $srcH) {
            return ['url' => $originalUrl, 'width' => $srcW, 'height' => $srcH];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $stub = preg_replace('/\.'.preg_quote($ext, '/').'$/', '', $path);
        $variantPath = $stub.'-'.$variant.'.jpg';

        if (!is_file($variantPath)) {
            $this->resizeToFile($path, $variantPath, $dim['w'], $dim['h'], $dim['cover']);
        }

        [$vw, $vh] = @getimagesize($variantPath) ?: [$dim['w'], $dim['h']];

        return ['url' => $this->pathToUrl($variantPath), 'width' => $vw, 'height' => $vh];
    }

    /** @return array{w: int, h: int, cover: bool} */
    private function variantDimensions(string $variant): array
    {
        return match ($variant) {
            'thumb' => ['w' => 520, 'h' => 520, 'cover' => true],
            'card' => ['w' => 760, 'h' => 540, 'cover' => true],
            'square' => ['w' => 1000, 'h' => 1000, 'cover' => true],
            'big' => ['w' => 1400, 'h' => 1050, 'cover' => false],
            default => ['w' => 1200, 'h' => 900, 'cover' => false],
        };
    }

    private function resizeToFile(string $src, string $dest, int $tw, int $th, bool $cover): void
    {
        $srcImg = @imagecreatefromstring((string) file_get_contents($src));
        if (false === $srcImg) {
            return;
        }

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        if ($tw >= $srcW && $th >= $srcH && false === $cover) {
            $outImg = $srcImg;
            $dstW = $srcW;
            $dstH = $srcH;
        } else {
            if ($cover) {
                $scale = max($tw / $srcW, $th / $srcH);
                $dstW = (int) round($srcW * $scale);
                $dstH = (int) round($srcH * $scale);
            } else {
                $scale = min($tw / $srcW, $th / $srcH, 1);
                $dstW = max(1, (int) round($srcW * $scale));
                $dstH = max(1, (int) round($srcH * $scale));
            }

            $outImg = imagecreatetruecolor($dstW, $dstH);
            imagecopyresampled($outImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        }

        if ($cover && ($dstW > $tw || $dstH > $th)) {
            $cx = (int) (($dstW - $tw) / 2);
            $cy = (int) (($dstH - $th) / 2);
            $crop = imagecreatetruecolor($tw, $th);
            imagecopy($crop, $outImg, 0, 0, max(0, $cx), max(0, $cy), min($tw, $dstW), min($th, $dstH));
            imagedestroy($outImg);
            $outImg = $crop;
        }

        imageinterlace($outImg, true);
        imagejpeg($outImg, $dest, 84);

        imagedestroy($srcImg);
        imagedestroy($outImg);
    }

    public function urlToPath(string $url): ?string
    {
        if (!str_starts_with($url, '/media/')) {
            return null;
        }

        return $this->mediaDir.substr($url, strlen('/media'));
    }

    /** Directory used for uploads that belong to a not-yet-saved product. */
    public function tmpDir(string $token): string
    {
        $dir = $this->tmpDir.\DIRECTORY_SEPARATOR.$token;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Store a validated upload into the temporary (pre-save) area. */
    public function storeTempUpload(UploadedFile $file, string $token): string
    {
        $dir = $this->tmpDir($token);
        $ext = $this->extensionFor($file);
        $name = $this->uniqueBasename($dir, $ext);
        $file->move($dir, $name.'.'.$ext);

        return sprintf('/uploads/tmp/%s/%s.%s', $token, $name, $ext);
    }

    /**
     * Move a temporary upload into a product folder (product already exists).
     * Returns the permanent url.
     */
    public function adoptTemp(string $tmpUrl, int $productId): string
    {
        $from = $this->publicPath($tmpUrl);
        $dir = $this->ensureProductDir($productId);
        $name = basename($from);
        $to = $dir.\DIRECTORY_SEPARATOR.$name;

        if (is_file($from) && rename($from, $to)) {
            $this->rmEmptyDirs(\dirname($from));

            return sprintf('/media/%d/%s', $productId, rawurlencode($name));
        }

        throw new \RuntimeException('Impossible de déplacer l\'image temporaire.');
    }

    public function publicPath(string $url): string
    {
        return dirname(__DIR__, 2).'/public'.str_replace('..', '', $url);
    }

    /**
     * Remove every cached variant of a file (called after a crop/overwrite so
     * the next render regenerates clean thumbnails).
     */
    public function purgeVariants(string $url): void
    {
        $path = $this->publicPath($url);
        if (!is_file($path)) {
            return;
        }
        $stub = preg_replace('/\.[a-zA-Z0-9]+$/', '', $path);
        foreach (glob($stub.'-*.jpg') ?: [] as $variant) {
            @unlink($variant);
        }
    }

    public function removeProductFiles(int $productId): void
    {
        $dir = $this->productDir($productId);
        if (is_dir($dir)) {
            foreach (glob($dir.\DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    public function deleteFile(string $url): void
    {
        $path = $this->publicPath($url);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->purgeVariants($url);
    }

    /**
     * Crop an image (percentages of the source) and overwrite the source file.
     * Returns the same url once applied.
     */
    public function crop(string $url, float $x, float $y, float $w, float $h): string
    {
        $path = $this->publicPath($url);
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier image introuvable.');
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (false === $img) {
            throw new \RuntimeException('Image illisible.');
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $cw = max(1, (int) round($srcW * max(0.05, min(1, $w))));
        $ch = max(1, (int) round($srcH * max(0.05, min(1, $h))));
        $cx = (int) round($srcW * max(0, min(1, $x)));
        $cy = (int) round($srcH * max(0, min(1, $y)));
        $cx = min($cx, $srcW - $cw);
        $cy = min($cy, $srcH - $ch);

        $out = imagecreatetruecolor($cw, $ch);
        imagecopy($out, $img, 0, 0, $cx, $cy, $cw, $ch);
        imagejpeg($out, $path, 90);
        imagedestroy($img);
        imagedestroy($out);

        // touch the file so variant cache becomes stale
        touch($path);
        $this->purgeVariants($url);

        return $url;
    }

    private function rmEmptyDirs(string $dir): void
    {
        while (is_dir($dir) && str_starts_with($dir, $this->tmpDir) && count(glob($dir.\DIRECTORY_SEPARATOR.'*') ?: []) === 0) {
            @rmdir($dir);
            $dir = \dirname($dir);
        }
    }

    private function pathToUrl(string $path): string
    {
        return '/media/'.ltrim(str_replace('\\', '/', substr($path, strlen($this->mediaDir))), '/');
    }

    /** @return array{width: int, height: int} */
    public function dimensionsOf(string $url): array
    {
        $path = $this->urlToPath($url);
        $info = null !== $path && is_file($path) ? @getimagesize($path) : null;

        return ['width' => $info[0] ?? 0, 'height' => $info[1] ?? 0];
    }
}
