<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

/**
 * Azar con semilla (xorshift32). Propio y no `mt_rand` para que dos combates
 * con la misma semilla den lo mismo sin tocar estado global: lo usa
 * `make balance` y lo usarían los tests.
 */
final class Rng
{
    private int $state;

    public function __construct(?int $seed = null)
    {
        $this->state = ($seed ?? random_int(1, 0x7fffffff)) & 0xffffffff ?: 0x9e3779b9;
    }

    /** Un número en [0, 1). */
    public function float(): float
    {
        $x  = $this->state;
        $x ^= ($x << 13) & 0xffffffff;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xffffffff;
        $this->state = $x & 0xffffffff;

        return $this->state / 4294967296;
    }

    public function chance(float $p): bool
    {
        return $this->float() < $p;
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }
}
