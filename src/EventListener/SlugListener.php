<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Category;
use App\Entity\Product;
use App\Service\SlugService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class SlugListener
{
    public function __construct(private readonly SlugService $slugs)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Product) {
            $this->sync($entity, Product::class, $entity->getName(), 0);
        } elseif ($entity instanceof Category) {
            $this->sync($entity, Category::class, $entity->getName(), 0);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Product) {
            if (null === $entity->getSlug()) {
                $this->sync($entity, Product::class, $entity->getName(), (int) $entity->getId());
            }
        } elseif ($entity instanceof Category) {
            if (null === $entity->getSlug()) {
                $this->sync($entity, Category::class, $entity->getName(), (int) $entity->getId());
            }
        }
    }

    private function sync(object $entity, string $class, ?string $name, int $excludeId): void
    {
        if (null === $entity->getSlug() && null !== $name) {
            $entity->setSlug($this->slugs->unique($name, $class, $excludeId));
        }
    }
}
