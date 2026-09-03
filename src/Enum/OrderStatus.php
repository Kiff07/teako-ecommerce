<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::PAID => 'Payée',
            self::SHIPPED => 'Expédiée',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#f59e0b',
            self::PAID => '#3b82f6',
            self::SHIPPED => '#8b5cf6',
            self::DELIVERED => '#22c55e',
            self::CANCELLED => '#ef4444',
        };
    }
}
