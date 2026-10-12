<?php

declare(strict_types=1);

namespace App\Characters\Domain;

final class Stats
{
    public function __construct(
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spd,
    ) {}

    public function plus(Stats $other, int $times = 1): self
    {
        return new self(
            $this->hp + $other->hp * $times,
            $this->atk + $other->atk * $times,
            $this->def + $other->def * $times,
            $this->spd + $other->spd * $times,
        );
    }

    public function scaled(float $factor, bool $atkOnly = false): self
    {
        if ($atkOnly) {
            return new self($this->hp, (int) round($this->atk * $factor), $this->def, $this->spd);
        }

        return new self(
            (int) round($this->hp * $factor),
            (int) round($this->atk * $factor),
            (int) round($this->def * $factor),
            (int) round($this->spd * $factor),
        );
    }

    /** Un número para comparar de un vistazo lo fuerte que es alguien. */
    public function power(): int
    {
        return (int) round($this->hp / 4 + $this->atk * 3 + $this->def * 2 + $this->spd);
    }

    public function toArray(): array
    {
        return ['hp' => $this->hp, 'atk' => $this->atk, 'def' => $this->def, 'spd' => $this->spd, 'power' => $this->power()];
    }
}
