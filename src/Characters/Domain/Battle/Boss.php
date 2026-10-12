<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\Stats;

/**
 * Los jefes, en orden. Cada uno se desbloquea al vencer al anterior **con ese
 * mismo personaje**: la progresión es de cada héroe, no de la cuenta.
 *
 * Los números salen de `make balance`, que enfrenta cada clase a cada jefe:
 * al nivel recomendado se gana más o menos la mitad de las veces (la rata,
 * que es el tutorial, bastante más), y dos niveles por encima casi siempre.
 * Que cada jefe se le dé mejor a unas clases que a otras es a propósito.
 */
enum Boss: string
{
    case RatQueen = 'rat_queen';
    case Goblin   = 'goblin';
    case Skeleton = 'skeleton';
    case Spider   = 'spider';
    case Golem    = 'golem';
    case Lich     = 'lich';
    case Dragon   = 'dragon';

    public function name(): string
    {
        return match ($this) {
            self::RatQueen => 'Reina de las Ratas',
            self::Goblin   => 'Gruñak el Goblin',
            self::Skeleton => 'Caballero Hueso',
            self::Spider   => 'Tejedora Abisal',
            self::Golem    => 'Gólem de Obsidiana',
            self::Lich     => 'Liche Eterno',
            self::Dragon   => 'Vermithrax',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::RatQueen => '🐀',
            self::Goblin   => '👺',
            self::Skeleton => '💀',
            self::Spider   => '🕷️',
            self::Golem    => '🗿',
            self::Lich     => '🧟',
            self::Dragon   => '🐉',
        };
    }

    public function lore(): string
    {
        return match ($this) {
            self::RatQueen => 'Gobierna las alcantarillas desde un trono de queso rancio.',
            self::Goblin   => 'Pega primero y pregunta nunca. Sobre todo pega.',
            self::Skeleton => 'Juró proteger el castillo. El castillo cayó hace tres siglos.',
            self::Spider   => 'Teje en la oscuridad redes que atrapan hasta la luz.',
            self::Golem    => 'Roca volcánica animada por un éter muy, muy antiguo.',
            self::Lich     => 'Escondió su alma en una caja. Nadie sabe dónde está la caja.',
            self::Dragon   => 'El dragón que se bebió el éter del mundo. Tiene sed.',
        };
    }

    public function stats(): Stats
    {
        return match ($this) {
            self::RatQueen => new Stats(hp: 133,  atk: 15,  def: 5,  spd: 7),
            self::Goblin   => new Stats(hp: 235,  atk: 22,  def: 10, spd: 9),
            self::Skeleton => new Stats(hp: 374,  atk: 28,  def: 18, spd: 7),
            self::Spider   => new Stats(hp: 565,  atk: 40,  def: 21, spd: 15),
            self::Golem    => new Stats(hp: 1383, atk: 68,  def: 42, spd: 5),
            self::Lich     => new Stats(hp: 1881, atk: 105, def: 32, spd: 13),
            self::Dragon   => new Stats(hp: 2726, atk: 124, def: 48, spd: 17),
        };
    }

    /** Nivel con el que la pelea está más o menos igualada. */
    public function recommendedLevel(): int
    {
        return match ($this) {
            self::RatQueen => 1,
            self::Goblin   => 3,
            self::Skeleton => 5,
            self::Spider   => 8,
            self::Golem    => 12,
            self::Lich     => 16,
            self::Dragon   => 22,
        };
    }

    /** Éter que se gana al vencer. Al perder se pierde una cuarta parte. */
    public function reward(): int
    {
        return match ($this) {
            self::RatQueen => 15,
            self::Goblin   => 35,
            self::Skeleton => 70,
            self::Spider   => 120,
            self::Golem    => 220,
            self::Lich     => 360,
            self::Dragon   => 700,
        };
    }

    public function penalty(): int
    {
        return (int) ceil($this->reward() / 4);
    }

    public function specialName(): string
    {
        return match ($this) {
            self::RatQueen => 'Mordisco infecto',
            self::Goblin   => 'Garrotazo',
            self::Skeleton => 'Carga sepulcral',
            self::Spider   => 'Veneno abisal',
            self::Golem    => 'Puño sísmico',
            self::Lich     => 'Nova de almas',
            self::Dragon   => 'Aliento de éter',
        };
    }

    /** El jefe anterior, que hay que haber vencido para poder retar a éste. */
    public function previous(): ?self
    {
        $cases = self::cases();
        $i     = array_search($this, $cases, true);

        return $i > 0 ? $cases[$i - 1] : null;
    }
}
