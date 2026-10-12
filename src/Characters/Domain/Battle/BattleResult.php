<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

final class BattleResult
{
    /** @param array<int, array<string, mixed>> $log */
    public function __construct(
        public readonly bool $won,
        public readonly array $log,
        public readonly int $heroMaxHp,
        public readonly int $bossMaxHp,
        public readonly int $rounds,
    ) {}
}
