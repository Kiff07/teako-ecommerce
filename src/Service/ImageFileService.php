<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductImage;

/**
 * Resolves an image url (+w/h) for a product image, generating the cached
 * variant on first use. Effectively the "image cache" of the app: a single
 * canonical place producing every display size.
 */
class ImageFileService
{
    public function __construct(private readonly ImageProcessor $processor)
    {
    }

    /** @return array{url: string, width: int, height: int} */
    public function variant(ProductImage $image, string $variant): array
    {
        $filename = $image->getFilename();
        if (null === $filename || '' === $filename) {
            return $this->placeholder();
        }

        if (str_starts_with($filename, 'http')) {
            return ['url' => $filename, 'width' => $image->getWidth() ?? 0, 'height' => $image->getHeight() ?? 0];
        }

        return $this->processor->variant($filename, $variant);
    }

    /** @return array{url: string, width: int, height: int} */
    public function placeholder(): array
    {
        return ['url' => '/img/placeholder.svg', 'width' => 900, 'height' => 640];
    }
}
