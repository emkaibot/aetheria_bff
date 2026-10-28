<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

/**
 * Rareza de un objeto: multiplica sus valores, decide cuántos atributos
 * secundarios trae y cuánto vale. El color lo pinta la app con el id.
 */
enum Rarity: string
{
    case Common     = 'common';
    case Uncommon   = 'uncommon';
    case Rare       = 'rare';
    case Epic       = 'epic';
    case Legendary  = 'legendary';
    case Mythic     = 'mythic';
    case Primordial = 'primordial';

    public function label(): string
    {
        return match ($this) {
            self::Common     => 'Común',
            self::Uncommon   => 'Poco común',
            self::Rare       => 'Raro',
            self::Epic       => 'Épico',
            self::Legendary  => 'Legendario',
            self::Mythic     => 'Mítico',
            self::Primordial => 'Primordial',
        };
    }

    public function multiplier(): float
    {
        return match ($this) {
            self::Common     => 1.0,
            self::Uncommon   => 1.15,
            self::Rare       => 1.3,
            self::Epic       => 1.5,
            self::Legendary  => 1.75,
            self::Mythic     => 2.0,
            self::Primordial => 2.3,
        };
    }

    public function secondaries(): int
    {
        return match ($this) {
            self::Common     => 0,
            self::Uncommon   => 1,
            self::Rare       => 2,
            self::Epic       => 3,
            self::Legendary  => 4,
            self::Mythic     => 4,
            self::Primordial => 5,
        };
    }

    /** Oro base: al venderlo (por nivel de objeto) y al mejorarlo (por nivel de mejora). */
    public function value(): int
    {
        return match ($this) {
            self::Common     => 5,
            self::Uncommon   => 10,
            self::Rare       => 20,
            self::Epic       => 40,
            self::Legendary  => 80,
            self::Mythic     => 150,
            self::Primordial => 300,
        };
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
