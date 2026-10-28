<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Hero;
use App\Characters\Domain\Perk;
use App\Characters\Domain\Progression;
use App\Characters\Domain\Stats;

/**
 * Quien entra en un combate por el bando del jugador, ya con todo sumado:
 * nivel, equipamiento y ventajas. Lo arma quien llama (un combate contra un
 * jefe, una expedición, `make balance`) y el simulador sólo pelea.
 */
final class Combatant
{
    /** @param Perk[] $perks sólo las tiene el líder */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly Stats $stats,
        public readonly Role $role,
        public readonly int $level,
        public readonly array $perks = [],
        public readonly Bonus $bonus = new Bonus(),
    ) {}

    /**
     * El líder: clase, éter (nivel y ventajas) y lo que lleve equipado.
     */
    public static function leader(string $name, CharacterClass $class, int $energy, ?Stats $gear = null, Bonus $bonus = new Bonus()): self
    {
        $stats = Progression::stats($class, $energy);
        if ($gear !== null) {
            $stats = $stats->plus($gear);
        }

        return new self('leader', $name, $stats, $class->role(), Progression::level($energy), Perk::unlockedBy($energy), $bonus);
    }

    public static function hero(Hero $hero, int $exp, ?Stats $gear = null, Bonus $bonus = new Bonus(), int $stars = 1): self
    {
        $stats = Progression::heroStats($hero, $exp, $stars);
        if ($gear !== null) {
            $stats = $stats->plus($gear);
        }

        return new self($hero->value, $hero->label(), $stats, $hero->role(), Progression::level($exp), bonus: $bonus);
    }
}
