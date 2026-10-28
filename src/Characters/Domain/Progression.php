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

    /** Las del líder: clase + crecimiento por nivel + ventajas que tocan números. */
    public static function stats(CharacterClass $class, int $energy): Stats
    {
        $stats = $class->base()->plus($class->growth(), self::level($energy) - 1);

        if ($energy >= Perk::Spark->threshold()) {
            $stats = $stats->scaled(1.08, atkOnly: true);
        }
        if ($energy >= Perk::Aura->threshold()) {
            $stats = $stats->scaled(1.15);
        }

        return $stats;
    }

    /**
     * Las de un héroe del equipo. Su EXP sube por la misma curva que el éter
     * del líder, así que `level()` y `describe()` valen para los dos.
     */
    public static function heroStats(Hero $hero, int $exp): Stats
    {
        return $hero->base()->plus($hero->growth(), self::level($exp) - 1);
    }

    /**
     * Nivel medio de quien pelea, el líder y su equipo: con él se mide la
     * dificultad de un jefe y lo que rinde una expedición.
     *
     * @param CharacterHero[] $party
     */
    public static function teamLevel(int $energy, array $party): int
    {
        $levels = [self::level($energy), ...array_map(fn (CharacterHero $h) => $h->level(), $party)];

        return (int) round(array_sum($levels) / count($levels));
    }
}
