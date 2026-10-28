<?php

declare(strict_types=1);

namespace App\Characters\Domain\Expeditions;

/**
 * Lo que da una hora de expedición. Un equipo por debajo del nivel que pide
 * el sitio rinde menos (hasta un 30 %); uno por encima, algo más (hasta un
 * 120 %).
 */
final class Rates
{
    public function __construct(
        public readonly int $requiredLevel,
        public readonly float $efficiency,
        public readonly int $gold,
        public readonly int $exp,
        public readonly float $items,
        public readonly int $enemies,
        public readonly float $bosses,
    ) {}

    public static function for(Zone $zone, Difficulty $difficulty, int $teamLevel): self
    {
        $boss     = $zone->boss();
        $required = $boss->recommendedLevel() + $difficulty->extraLevels();
        $eff      = max(0.3, min(1.2, $teamLevel / $required));
        $mult     = $difficulty->rewardMultiplier();

        return new self(
            requiredLevel: $required,
            efficiency: round($eff, 2),
            gold: (int) round($boss->gold() * 3 * $eff * $mult),
            exp: (int) round($boss->exp() * 2 * $eff * $mult),
            items: round(2 * $eff * $mult, 1),
            enemies: (int) round(240 * $eff),
            bosses: round(1.5 * $eff, 1),
        );
    }

    public function toArray(): array
    {
        return [
            'required_level' => $this->requiredLevel,
            'efficiency'     => $this->efficiency,
            'gold'           => $this->gold,
            'exp'            => $this->exp,
            'items'          => $this->items,
            'enemies'        => $this->enemies,
            'bosses'         => $this->bosses,
        ];
    }
}
