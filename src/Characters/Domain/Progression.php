<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * De éter a nivel, rango y estadísticas. Vive aquí y sólo aquí: la app pinta
 * lo que recibe y no hace cuentas, así que cambiar la curva no obliga a
 * publicar una versión nueva.
 *
 * Nivel = ⌊√(éter / 10)⌋ + 1, con tope en 50. El nivel N pide 10·(N−1)² de
 * éter: 10 para el 2, 40 para el 3, 1.000 para el 11. Cada nivel cuesta un
 * poco más que el anterior sin que la curva se dispare.
 */
final class Progression
{
    public const MAX_LEVEL = 50;

    public static function level(int $energy): int
    {
        return min(self::MAX_LEVEL, (int) floor(sqrt(max(0, $energy) / 10)) + 1);
    }

    public static function energyForLevel(int $level): int
    {
        return 10 * ($level - 1) ** 2;
    }

    public static function rank(int $level): string
    {
        return match (true) {
            $level >= 25 => 'Leyenda',
            $level >= 15 => 'Campeón',
            $level >= 10 => 'Veterano',
            $level >= 5  => 'Adepto',
            default      => 'Aprendiz',
        };
    }

    /** @return array{level: int, rank: string, current: int, next: ?int, progress: float} */
    public static function describe(int $energy): array
    {
        $level   = self::level($energy);
        $current = self::energyForLevel($level);
        $next    = $level < self::MAX_LEVEL ? self::energyForLevel($level + 1) : null;

        return [
            'level'    => $level,
            'rank'     => self::rank($level),
            'current'  => $current,
            'next'     => $next,
            'progress' => $next === null ? 1.0 : round(($energy - $current) / ($next - $current), 3),
        ];
    }

    /**
     * Estadísticas finales: clase + crecimiento por nivel + ventajas que tocan
     * números + el equipo comprado en la tienda.
     *
     * @param Item[] $items
     */
    public static function stats(CharacterClass $class, int $energy, array $items = []): Stats
    {
        $stats = $class->base()->plus($class->growth(), self::level($energy) - 1);

        if ($energy >= Perk::Spark->threshold()) {
            $stats = $stats->scaled(1.08, atkOnly: true);
        }
        if ($energy >= Perk::Aura->threshold()) {
            $stats = $stats->scaled(1.15);
        }

        $mult = ['hp' => 1.0, 'atk' => 1.0, 'def' => 1.0, 'spd' => 1.0];
        foreach ($items as $item) {
            foreach ($item->bonus() as $stat => $m) {
                $mult[$stat] *= $m;
            }
        }

        return new Stats(
            (int) round($stats->hp * $mult['hp']),
            (int) round($stats->atk * $mult['atk']),
            (int) round($stats->def * $mult['def']),
            (int) round($stats->spd * $mult['spd']),
        );
    }
}
