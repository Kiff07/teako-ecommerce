<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var array<int, bool>|null */
    private ?array $favCache = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_fav', [$this, 'isFav']),
            new TwigFunction('fav_ids', [$this, 'favIds']),
            new TwigFunction('store_categories', [$this, 'storeCategories']),
            new TwigFunction('money', [$this, 'money']),
        ];
    }

    public function money(int|float $cents): string
    {
        return number_format((int) round($cents / 100), 0, ',', ' ').' Ar';
    }

    public function isFav(Product|int $product): bool
    {
        $id = $product instanceof Product ? (int) $product->getId() : (int) $product;

        return in_array($id, $this->favIds(), true);
    }

    /** @return list<int> */
    public function favIds(): array
    {
        if (null !== $this->favCache) {
            return array_keys($this->favCache);
        }

        $raw = (string) ($this->requestStack->getMainRequest()?->cookies->get('tk_favs', ''));
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
        $this->favCache = [];
        foreach ($ids as $id) {
            $this->favCache[$id] = true;
        }

        return array_keys($this->favCache);
    }

    /** @return list<\App\Entity\Category> */
    public function storeCategories(): array
    {
        return $this->categories->findBy([], ['name' => 'ASC']);
    }
}
