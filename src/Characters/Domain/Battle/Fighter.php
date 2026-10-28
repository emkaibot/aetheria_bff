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
    /** Ataques del jefe que aún atrae hacia sí (el Muro de escudos del guardián). */
    public int $taunt = 0;
    /** Daño que absorbe antes de perder vida (el Caparazón rúnico de Bastión). */
    public int $shield = 0;

    /**
     * @param string $key   `leader`, el id del héroe o `boss`: así lo nombra el registro
     * @param Perk[] $perks sólo las tiene el líder
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly Stats $stats,
        public readonly ?Role $role = null,
        public readonly array $perks = [],
        public readonly int $level = 1,
        public readonly Bonus $bonus = new Bonus(),
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

    /** Cura (más su curación recibida) sin pasar de su vida máxima. Devuelve lo curado. */
    public function heal(int $amount): int
    {
        $before   = $this->hp;
        $this->hp = min($this->stats->hp, $this->hp + (int) round($amount * (1 + $this->bonus->healing)));

        return $this->hp - $before;
    }

    public function critChance(): float
    {
        return min(Bonus::MAX_CRIT, $this->bonus->crit() + ($this->has(Perk::Critical) ? 0.15 : 0));
    }

    public function dodgeChance(): float
    {
        return min(Bonus::MAX_DODGE, $this->bonus->dodge() + ($this->has(Perk::Reflexes) ? 0.10 : 0));
    }

    public function lifesteal(): float
    {
        return min(Bonus::MAX_LIFESTEAL, $this->bonus->lifesteal() + ($this->has(Perk::Vampiric) ? 0.12 : 0));
    }
}
