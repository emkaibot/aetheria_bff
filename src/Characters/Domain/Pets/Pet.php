<?php

declare(strict_types=1);

namespace App\Characters\Domain\Pets;

use App\Characters\Domain\Battle\Boss;

/**
 * Las mascotas. Cada una tiene tres formas (evoluciona a nivel 20 y a 40),
 * una bonificación pasiva para todo el equipo y una habilidad que usa sola en
 * combate. Suben con la EXP de los combates, como los héroes, y van como
 * mucho tres a la vez.
 *
 * Azurion viene de serie; el resto se libera venciendo a su jefe o se compra
 * con oro. Los ids son los nombres de su arte (`<id>_1`, `_2`, `_3`).
 */
enum Pet: string
{
    case Azurion = 'azurion';
    case Lunaris = 'lunaris';
    case Solaria = 'solaria';
    case Bastion = 'bastion';
    case Noctua  = 'noctua';
    case Umbra   = 'umbra';

    public const ACTIVE_MAX = 3;
    public const STARTERS   = [self::Azurion];
    /** Niveles a los que evoluciona. */
    public const EVOLVES_AT = [20, 40];

    /** @return string[] el nombre de cada forma */
    public function forms(): array
    {
        return match ($this) {
            self::Azurion => ['Azulín', 'Azuriel', 'Azurion'],
            self::Lunaris => ['Lunis', 'Lunar', 'Lunaris'],
            self::Solaria => ['Chispa', 'Ígnea', 'Solaria'],
            self::Bastion => ['Pedrusco', 'Runagal', 'Bastión'],
            self::Noctua  => ['Plumín', 'Noctua', 'Oráculo'],
            self::Umbra   => ['Sombrita', 'Umbra', 'Nictálope'],
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Azurion => 'Dragón del fulgor violeta',
            self::Lunaris => 'Lobo blanco lunar',
            self::Solaria => 'Fénix de fuego',
            self::Bastion => 'Tortuga de roca rúnica',
            self::Noctua  => 'Búho arcano',
            self::Umbra   => 'Pantera de sombra',
        };
    }

    /** 1, 2 o 3: la forma que tiene a ese nivel. */
    public static function stage(int $level): int
    {
        return $level >= self::EVOLVES_AT[1] ? 3 : ($level >= self::EVOLVES_AT[0] ? 2 : 1);
    }

    public function name(int $level): string
    {
        return $this->forms()[self::stage($level) - 1];
    }

    /** Qué mejora a todo el equipo: un atributo de equipamiento o `spd`/`def` en %. */
    public function bonusAttribute(): string
    {
        return match ($this) {
            self::Azurion => 'skill',
            self::Lunaris => 'spd',
            self::Solaria => 'healing',
            self::Bastion => 'def',
            self::Noctua  => 'crit',
            self::Umbra   => 'lifesteal',
        };
    }

    public function bonusLabel(): string
    {
        return match ($this) {
            self::Azurion => 'Daño de habilidades del equipo',
            self::Lunaris => 'Velocidad del equipo',
            self::Solaria => 'Curación recibida del equipo',
            self::Bastion => 'Defensa del equipo',
            self::Noctua  => 'Probabilidad crítica del equipo',
            self::Umbra   => 'Robo de vida del equipo',
        };
    }

    /** @return int[] la bonificación en cada forma, en % (o pp en la crítica) */
    public function bonusValues(): array
    {
        return match ($this) {
            self::Azurion => [2, 4, 6],
            self::Lunaris => [3, 6, 9],
            self::Solaria => [4, 8, 12],
            self::Bastion => [3, 6, 9],
            self::Noctua  => [2, 4, 6],
            self::Umbra   => [1, 2, 3],
        };
    }

    public function bonus(int $level): int
    {
        return $this->bonusValues()[self::stage($level) - 1];
    }

    public function specialName(): string
    {
        return match ($this) {
            self::Azurion => 'Aliento de amatista',
            self::Lunaris => 'Aullido plateado',
            self::Solaria => 'Renacer',
            self::Bastion => 'Caparazón rúnico',
            self::Noctua  => 'Mirada del oráculo',
            self::Umbra   => 'Zarpazo umbrío',
        };
    }

    /** Cada cuántas rondas la usa; null en la que salta una sola vez cuando hace falta. */
    public function every(): ?int
    {
        return match ($this) {
            self::Azurion => 5,
            self::Lunaris => 4,
            self::Solaria => null,
            self::Bastion => 6,
            self::Noctua  => 5,
            self::Umbra   => 4,
        };
    }

    /** @return int[] la fuerza de la habilidad en cada forma, en % */
    public function specialValues(): array
    {
        return match ($this) {
            self::Azurion => [60, 80, 100],
            self::Lunaris => [0, 0, 0],
            self::Solaria => [20, 35, 50],
            self::Bastion => [10, 15, 20],
            self::Noctua  => [0, 0, 0],
            self::Umbra   => [80, 110, 140],
        };
    }

    public function specialValue(int $level): int
    {
        return $this->specialValues()[self::stage($level) - 1];
    }

    /** `{v}` es el sitio de los valores de las tres formas. */
    public function specialDescription(): string
    {
        return match ($this) {
            self::Azurion => 'Cada 5 rondas golpea al enemigo con un {v} del ataque medio del equipo.',
            self::Lunaris => 'Cada 4 rondas el equipo entero actúa antes que el enemigo.',
            self::Solaria => 'Una vez por combate revive al primer héroe caído con un {v} de su vida.',
            self::Bastion => 'Cada 6 rondas da al equipo un escudo del {v} de su vida.',
            self::Noctua  => 'Cada 5 rondas el siguiente golpe del equipo es crítico seguro.',
            self::Umbra   => 'Cada 4 rondas zarpa al enemigo con un {v} del ataque medio del equipo.',
        };
    }

    public function unlockedBy(): ?Boss
    {
        return match ($this) {
            self::Azurion => null,
            self::Lunaris => Boss::Skeleton,
            self::Solaria => Boss::Spider,
            self::Bastion => Boss::Golem,
            self::Noctua  => Boss::Lich,
            self::Umbra   => Boss::Dragon,
        };
    }

    public function price(): int
    {
        return match ($this) {
            self::Azurion => 0,
            self::Lunaris => 400,
            self::Solaria => 900,
            self::Bastion => 1500,
            self::Noctua  => 2200,
            self::Umbra   => 3000,
        };
    }

    public static function forBoss(Boss $boss): ?self
    {
        foreach (self::cases() as $pet) {
            if ($pet->unlockedBy() === $boss) {
                return $pet;
            }
        }

        return null;
    }
}
