<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\Gear\Gear;
use App\Characters\Domain\Gear\GearForge;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Loadout;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\Gear\Slot;
use App\Characters\Domain\Hero;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Domain\Progression;

/**
 * El equipo típico con el que se llega a cada jefe sin comprar nada: los
 * héroes y las mascotas que liberaron los jefes anteriores y un
 * equipamiento que crece con ellos. Con él se calibran los jefes
 * (`make balance --calibrate`) y se comprueba que siguen en su sitio.
 */
final class Benchmark
{
    /** @return array<int, array{0: Pet, 1: int}> las mascotas típicas, a `$level`, como las pide el simulador */
    public static function pets(Boss $boss, int $level): array
    {
        return array_map(fn (Pet $p) => [$p, $level], self::petList($boss));
    }

    /** @return Combatant[] el líder y su equipo típico, todos a `$level` y con el equipamiento típico */
    public static function team(CharacterClass $class, Boss $boss, int $level): array
    {
        $exp   = Progression::energyForLevel($level);
        $leader = self::gear($boss, $class->role());
        $team  = [Combatant::leader('Prueba', $class, $exp, gear: Loadout::stats($leader), bonus: Loadout::bonus($leader))];
        foreach (self::party($boss) as $hero) {
            $gear   = self::gear($boss, $hero->role());
            $team[] = Combatant::hero($hero, $exp, Loadout::stats($gear), Loadout::bonus($gear));
        }

        return $team;
    }

    /**
     * Los héroes con los que se llega a un jefe sin comprar a nadie: los
     * iniciales más los que liberaron los jefes anteriores.
     *
     * @return Hero[]
     */
    public static function party(Boss $boss): array
    {
        $party = Hero::STARTERS;
        foreach (self::beatenBefore($boss) as $b) {
            if (($hero = Hero::forBoss($b)) !== null) {
                $party[] = $hero;
            }
        }

        return array_slice($party, 0, CharacterHero::PARTY_MAX);
    }

    /** @return Pet[] las mascotas de ese momento, como mucho las que caben */
    private static function petList(Boss $boss): array
    {
        $pets = Pet::STARTERS;
        foreach (self::beatenBefore($boss) as $b) {
            if (($pet = Pet::forBoss($b)) !== null) {
                $pets[] = $pet;
            }
        }

        return array_slice($pets, 0, Pet::ACTIVE_MAX);
    }

    /**
     * Las ranuras que se suelen tener llenas al llegar a un jefe: ninguna en
     * la rata (aún no ha caído nada) y dos más por cada jefe, hasta las ocho.
     *
     * @return Slot[]
     */
    public static function slots(Boss $boss): array
    {
        return array_slice(Slot::cases(), 0, min(8, 2 * self::tier($boss)));
    }

    /**
     * El equipamiento típico: las ranuras de `slots`, del nivel del jefe
     * anterior (de donde salió) y de una rareza que sube con el avance.
     *
     * @return GearItem[]
     */
    public static function gear(Boss $boss, Role $role): array
    {
        $tier   = self::tier($boss);
        $level  = $boss->previous()?->recommendedLevel() ?? 1;
        $rarity = match (true) {
            $tier >= 6 => Rarity::Epic,
            $tier >= 4 => Rarity::Rare,
            $tier >= 2 => Rarity::Uncommon,
            default    => Rarity::Common,
        };
        // Sin persistir: GearItem sólo necesita un personaje al que apuntar.
        $owner = new Character('00000000-0000-0000-0000-000000000000', 'balance', 'Prueba', CharacterClass::Warrior);
        $forge = new GearForge(new Rng(1000 + $tier));

        $items = [];
        foreach (self::slots($boss) as $slot) {
            $piece = $slot === Slot::Weapon
                ? self::weaponFor($role)
                : self::firstOf(fn (Gear $g) => $g->slot() === $slot && $g->family() === Gear::FAMILY_COMMON);
            $items[] = $forge->forge($owner, $piece, $level, $rarity);
        }

        return $items;
    }

    private static function weaponFor(Role $role): Gear
    {
        return self::firstOf(fn (Gear $g) => $g->role() === $role);
    }

    private static function firstOf(callable $match): Gear
    {
        foreach (Gear::cases() as $g) {
            if ($match($g)) {
                return $g;
            }
        }

        throw new \LogicException('Ninguna pieza encaja.');
    }

    private static function tier(Boss $boss): int
    {
        return array_search($boss, Boss::cases(), true);
    }

    /** @return Boss[] los jefes anteriores a este, del primero al último */
    private static function beatenBefore(Boss $boss): array
    {
        return array_slice(Boss::cases(), 0, self::tier($boss));
    }
}
