<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PriceExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->format(...)),
        ];
    }

    /** Formats an amount in cents (int) as Ariary: 25000 → "25 000 Ar". */
    public function format(float|int|string $cents): string
    {
        $ariary = (int) round(((float) $cents) / 100);

        return number_format($ariary, 0, ',', ' ').' Ar';
    }
}
