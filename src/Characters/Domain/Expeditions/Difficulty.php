<?php

declare(strict_types=1);

namespace App\Characters\Domain\Expeditions;

/** Más difícil pide más nivel y da más, y los objetos caen de más nivel. */
enum Difficulty: string
{
    case Normal    = 'normal';
    case Hard      = 'hard';
    case Nightmare = 'nightmare';

    public function label(): string
    {
        return match ($this) {
            self::Normal    => 'Normal',
            self::Hard      => 'Difícil',
            self::Nightmare => 'Pesadilla',
        };
    }

    /** Niveles por encima del recomendado del jefe. */
    public function extraLevels(): int
    {
        return match ($this) {
            self::Normal    => 0,
            self::Hard      => 4,
            self::Nightmare => 8,
        };
    }

    public function rewardMultiplier(): float
    {
        return match ($this) {
            self::Normal    => 1.0,
            self::Hard      => 1.6,
            self::Nightmare => 2.5,
        };
    }
}
