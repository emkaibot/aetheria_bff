<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\Character;
use Symfony\Component\Uid\Uuid;

/**
 * Hace los objetos que caen: elige la pieza según lo avanzado del sitio
 * (`$tier`, el índice del jefe: 0 la rata, 6 el dragón) y le tira rareza y
 * atributos. Las armas y las épicas caen desde el Caballero Hueso; el
 * firmamento, desde el Gólem y muy de vez en cuando.
 */
final class GearForge
{
    public function __construct(private readonly Rng $rng = new Rng()) {}

    public function drop(Character $character, int $level, int $tier): GearItem
    {
        $weights = [Gear::FAMILY_COMMON => 60];
        if ($tier >= 2) {
            $weights += [Gear::FAMILY_WEAPON => 15, Gear::FAMILY_EPIC => 25];
        }
        if ($tier >= 4) {
            $weights[Gear::FAMILY_FIRMAMENT] = 3;
        }

        $pieces = Gear::ofFamily($this->pick($weights));

        return $this->forge($character, $pieces[(int) floor($this->rng->float() * count($pieces))], $level);
    }

    public function forge(Character $character, Gear $gear, int $level, ?Rarity $rarity = null): GearItem
    {
        $rarity ??= Rarity::from($this->pick($gear->rarityWeights()));
        $mult     = $rarity->multiplier();

        $attributes = [];
        foreach ($gear->mains($level) as $attr => $value) {
            $attributes[$attr] = round($value * $mult, 1);
        }

        // Secundarios distintos de los principales, sin repetir.
        $pool = array_values(array_filter(Attribute::cases(), fn (Attribute $a) => !isset($attributes[$a->value])));
        for ($i = 0; $i < $rarity->secondaries() && $pool !== []; $i++) {
            $k    = (int) floor($this->rng->float() * count($pool));
            $attr = $pool[$k];
            array_splice($pool, $k, 1);
            $attributes[$attr->value] = round($attr->secondaryBase($level) * $mult * $this->rng->between(0.8, 1.2), 1);
        }

        return new GearItem(Uuid::v7()->toRfc4122(), $character, $gear, $rarity, max(1, $level), $attributes);
    }

    /** @param array<string, int> $weights */
    private function pick(array $weights): string
    {
        $roll = $this->rng->float() * array_sum($weights);
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll < 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
