<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\Perk;
use App\Characters\Domain\Stats;

/** Quien pelea, con su vida durante el combate. Sólo vive dentro del simulador. */
final class Fighter
{
    public int $hp;
    public int $actions = 0;
    public bool $usedSecondWind = false;
    public bool $hasHit = false;

    /** @param Perk[] $perks */
    public function __construct(
        public readonly string $name,
        public readonly Stats $stats,
        public readonly array $perks = [],
    ) {
        $this->hp = $stats->hp;
    }

    public function has(Perk $perk): bool
    {
        return in_array($perk, $this->perks, true);
    }

    public function alive(): bool
    {
        return $this->hp > 0;
    }

    public function heal(int $amount): int
    {
        $before   = $this->hp;
        $this->hp = min($this->stats->hp, $this->hp + $amount);

        return $this->hp - $before;
    }
}
