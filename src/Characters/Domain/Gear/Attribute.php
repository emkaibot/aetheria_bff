<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

/**
 * Lo que puede dar un objeto. Los cuatro primeros suman a las estadísticas;
 * el resto, al `Bonus` de combate, en puntos porcentuales.
 */
enum Attribute: string
{
    case Atk        = 'atk';
    case Def        = 'def';
    case Hp         = 'hp';
    case Spd        = 'spd';
    case Crit       = 'crit';
    case CritDamage = 'crit_damage';
    case Lifesteal  = 'lifesteal';
    case Healing    = 'healing';
    case Dodge      = 'dodge';
    case Skill      = 'skill';

    public function label(): string
    {
        return match ($this) {
            self::Atk        => 'Ataque',
            self::Def        => 'Defensa',
            self::Hp         => 'Vida',
            self::Spd        => 'Velocidad',
            self::Crit       => 'Probabilidad crítica',
            self::CritDamage => 'Daño crítico',
            self::Lifesteal  => 'Robo de vida',
            self::Healing    => 'Curación recibida',
            self::Dodge      => 'Esquiva',
            self::Skill      => 'Daño de habilidades',
        };
    }

    /** `pp` para las probabilidades, `%` para los multiplicadores, vacío para los números. */
    public function unit(): string
    {
        return match ($this) {
            self::Atk, self::Def, self::Hp, self::Spd => '',
            self::Crit, self::Dodge                   => 'pp',
            default                                   => '%',
        };
    }

    public function isStat(): bool
    {
        return in_array($this, [self::Atk, self::Def, self::Hp, self::Spd], true);
    }

    /** Lo que da como secundario en un objeto de nivel `$level`, antes de la rareza. */
    public function secondaryBase(int $level): float
    {
        return match ($this) {
            self::Atk        => 1 + 0.25 * $level,
            self::Def        => 1 + 0.2 * $level,
            self::Hp         => 5 + 2 * $level,
            self::Spd        => 1,
            self::Crit       => 2,
            self::CritDamage => 6,
            self::Lifesteal  => 1,
            self::Healing    => 3,
            self::Dodge      => 1.5,
            self::Skill      => 2,
        };
    }
}
