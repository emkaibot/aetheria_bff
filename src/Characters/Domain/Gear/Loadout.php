<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

use App\Characters\Domain\Battle\Bonus;
use App\Characters\Domain\Stats;

/** Lo que suma en combate lo que alguien lleva puesto. */
final class Loadout
{
    /** @param GearItem[] $items lo que lleva puesto una sola persona */
    public static function stats(array $items): Stats
    {
        $sum = self::sum($items);

        return new Stats((int) round($sum['hp']), (int) round($sum['atk']), (int) round($sum['def']), (int) round($sum['spd']));
    }

    /** @param GearItem[] $items */
    public static function bonus(array $items): Bonus
    {
        $sum = self::sum($items);

        return new Bonus(
            crit: $sum['crit'] / 100,
            critDamage: $sum['crit_damage'] / 100,
            lifesteal: $sum['lifesteal'] / 100,
            healing: $sum['healing'] / 100,
            dodge: $sum['dodge'] / 100,
            skill: $sum['skill'] / 100,
        );
    }

    /**
     * Los objetos de un personaje repartidos por quien los lleva.
     *
     * @param  GearItem[] $items
     * @return array<string, GearItem[]> `leader` o id de héroe => lo que lleva
     */
    public static function byWearer(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if ($item->getEquippedBy() !== null) {
                $out[$item->getEquippedBy()][] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, float> */
    private static function sum(array $items): array
    {
        $sum = array_fill_keys(array_map(fn (Attribute $a) => $a->value, Attribute::cases()), 0.0);
        foreach ($items as $item) {
            foreach ($item->attributes() as $attr => $value) {
                $sum[$attr] = ($sum[$attr] ?? 0.0) + $value;
            }
        }

        return $sum;
    }
}
