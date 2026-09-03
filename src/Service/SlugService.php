<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class SlugService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Build a URL-safe, unique slug from a name, optionally excluding an entity
     * (so re-saving the same entity keeps its slug).
     *
     * @param class-string $class
     */
    public function unique(string $name, string $class, int $excludeId = 0): string
    {
        $base = (string) $this->slugger->slug($name)->lower();
        if ('' === $base) {
            $base = 'element';
        }

        $slug = $base;
        $i = 2;
        while ($this->exists($slug, $class, $excludeId)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @param class-string $class */
    private function exists(string $slug, string $class, int $excludeId): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->where('e.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId > 0) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
