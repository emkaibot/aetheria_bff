<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

final class BattleResult
{
    /**
     * @param array<int, array<string, mixed>> $log
     * @param array<int, array{key: string, name: string, hero: ?string, role: ?string, level: int, max_hp: int}> $team
     *        el líder primero y luego su equipo
     */
    public function __construct(
        public readonly bool $won,
        public readonly array $log,
        public readonly int $heroMaxHp,
        public readonly int $bossMaxHp,
        public readonly int $rounds,
        public readonly array $team = [],
    ) {}
}
