<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

/**
 * Lo que alguien tiene en combate además de sus cuatro estadísticas: lo que
 * suman su equipamiento y las mascotas. Todo en tanto por uno (0.05 = 5 %).
 */
final class Bonus
{
    /** Topes: más allá de esto, un combate deja de tener gracia. */
    public const MAX_CRIT      = 0.6;
    public const MAX_DODGE     = 0.4;
    public const MAX_LIFESTEAL = 0.25;

    public function __construct(
        public readonly float $crit = 0.0,
        public readonly float $critDamage = 0.0,
        public readonly float $lifesteal = 0.0,
        public readonly float $healing = 0.0,
        public readonly float $dodge = 0.0,
        public readonly float $skill = 0.0,
    ) {}

    public function plus(self $o): self
    {
        return new self(
            $this->crit + $o->crit,
            $this->critDamage + $o->critDamage,
            $this->lifesteal + $o->lifesteal,
            $this->healing + $o->healing,
            $this->dodge + $o->dodge,
            $this->skill + $o->skill,
        );
    }

    public function crit(): float      { return min(self::MAX_CRIT, $this->crit); }
    public function dodge(): float     { return min(self::MAX_DODGE, $this->dodge); }
    public function lifesteal(): float { return min(self::MAX_LIFESTEAL, $this->lifesteal); }
}
