<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ProductImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('product_image', [ProductImageRuntime::class, 'url']),
            new TwigFilter('product_image_data', [ProductImageRuntime::class, 'data']),
            new TwigFilter('product_image_original', [ProductImageRuntime::class, 'original']),
            new TwigFilter('placeholder_image', fn () => '/img/placeholder.svg'),
        ];
    }
}
