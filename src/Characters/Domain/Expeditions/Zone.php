<?php

declare(strict_types=1);

namespace App\Characters\Domain\Expeditions;

use App\Characters\Domain\Battle\Boss;

/**
 * Los sitios a los que se manda al equipo de expedición. Cada uno se abre al
 * vencer a su jefe, y lo que da depende de él (su oro, su EXP, su nivel).
 */
enum Zone: string
{
    case Ruins = 'ruins';
    case Chasm = 'chasm';
    case Forge = 'forge';
    case Crypt = 'crypt';
    case Peaks = 'peaks';

    public function label(): string
    {
        return match ($this) {
            self::Ruins => 'Ciudadela Caída',
            self::Chasm => 'Abismo de Cristal',
            self::Forge => 'Forja Volcánica',
            self::Crypt => 'Cripta del Liche',
            self::Peaks => 'Cumbres del Dragón',
        };
    }

    public function boss(): Boss
    {
        return match ($this) {
            self::Ruins => Boss::Skeleton,
            self::Chasm => Boss::Spider,
            self::Forge => Boss::Golem,
            self::Crypt => Boss::Lich,
            self::Peaks => Boss::Dragon,
        };
    }

    /** Índice del jefe (0 la rata): decide qué objetos pueden caer. */
    public function tier(): int
    {
        return array_search($this->boss(), Boss::cases(), true);
    }
}
