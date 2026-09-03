<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Service\ImageFileService;

class ProductImageRuntime
{
    public function __construct(private readonly ImageFileService $images)
    {
    }

    /**
     * Twig filter: {{ image|product_image('card') }}
     * Returns the cached url of the wanted variant.
     */
    public function url(ProductImage|Product|null $subject, string $variant = 'card'): string
    {
        $image = $this->resolve($subject);

        return $image ? $this->images->variant($image, $variant)['url'] : $this->images->placeholder($variant)['url'];
    }

    /** @return array{url: string, width: int, height: int} */
    public function data(ProductImage|Product|null $subject, string $variant = 'card'): array
    {
        $image = $this->resolve($subject);

        return $image ? $this->images->variant($image, $variant) : $this->images->placeholder($variant);
    }

    /** URL of the original, un-cropped media file. */
    public function original(ProductImage|Product|null $subject): string
    {
        $image = $this->resolve($subject);
        if (null === $image || null === $image->getFilename()) {
            return '/img/placeholder.svg';
        }

        return $image->getFilename();
    }

    public function placeholder(): string
    {
        return $this->images->placeholder('card')['url'];
    }

    private function resolve(ProductImage|Product|null $subject): ?ProductImage
    {
        if ($subject instanceof ProductImage) {
            return $subject;
        }
        if ($subject instanceof Product) {
            return $subject->getMainImage();
        }

        return null;
    }
}
