<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

/** Las ocho ranuras del equipamiento de un héroe o del líder. */
enum Slot: string
{
    case Weapon = 'weapon';
    case Head   = 'head';
    case Chest  = 'chest';
    case Hands  = 'hands';
    case Boots  = 'boots';
    case Ring   = 'ring';
    case Amulet = 'amulet';
    case Relic  = 'relic';

    public function label(): string
    {
        return match ($this) {
            self::Weapon => 'Arma',
            self::Head   => 'Cabeza',
            self::Chest  => 'Pecho',
            self::Hands  => 'Manos',
            self::Boots  => 'Botas',
            self::Ring   => 'Anillo',
            self::Amulet => 'Amuleto',
            self::Relic  => 'Reliquia',
        };
    }
}
