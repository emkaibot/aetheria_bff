<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Role;

/**
 * Los héroes que acompañan al líder. Cada personaje tiene su propia colección:
 * empieza con tres y consigue el resto venciendo por primera vez al jefe que
 * guarda a cada uno, o pagándolo con oro antes de llegar a él.
 *
 * Suben de nivel con la EXP de los combates, no con éter: el éter es sólo del
 * líder y sólo cambia a mano. Los ids son los nombres de su arte en la app.
 */
enum Hero: string
{
    case Darius    = 'darius';
    case Seraphina = 'seraphina';
    case Aelyn     = 'aelyn';
    case Sylas     = 'sylas';
    case Thorin    = 'thorin';
    case Aldric    = 'aldric';
    case Morgana   = 'morgana';
    case Kairon    = 'kairon';
    case Malak     = 'malak';
    case Elyn      = 'elyn';

    /** Los que tiene un personaje nada más crearse, ya en su equipo. */
    public const STARTERS = [self::Darius, self::Seraphina, self::Aelyn];

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function role(): Role
    {
        return match ($this) {
            self::Darius    => Role::Guardian,
            self::Seraphina => Role::Cleric,
            self::Aelyn     => Role::Archer,
            self::Sylas     => Role::Rogue,
            self::Thorin    => Role::Berserker,
            self::Aldric    => Role::Warrior,
            self::Morgana   => Role::Mage,
            self::Kairon    => Role::Martial,
            self::Malak     => Role::Necromancer,
            self::Elyn      => Role::Druid,
        };
    }

    public function base(): Stats
    {
        return match ($this) {
            self::Darius    => new Stats(hp: 150, atk: 10, def: 15, spd: 5),
            self::Seraphina => new Stats(hp: 115, atk: 12, def: 10, spd: 7),
            self::Aelyn     => new Stats(hp: 95,  atk: 17, def: 6,  spd: 12),
            self::Sylas     => new Stats(hp: 100, atk: 16, def: 7,  spd: 13),
            self::Thorin    => new Stats(hp: 125, atk: 17, def: 8,  spd: 7),
            self::Aldric    => new Stats(hp: 130, atk: 14, def: 12, spd: 6),
            self::Morgana   => new Stats(hp: 90,  atk: 19, def: 5,  spd: 8),
            self::Kairon    => new Stats(hp: 110, atk: 15, def: 9,  spd: 14),
            self::Malak     => new Stats(hp: 100, atk: 17, def: 7,  spd: 9),
            self::Elyn      => new Stats(hp: 110, atk: 13, def: 9,  spd: 9),
        };
    }

    /** Lo que gana por cada nivel por encima del 1. */
    public function growth(): Stats
    {
        return match ($this) {
            self::Darius    => new Stats(hp: 34, atk: 2, def: 4, spd: 1),
            self::Seraphina => new Stats(hp: 26, atk: 3, def: 2, spd: 1),
            self::Aelyn     => new Stats(hp: 20, atk: 5, def: 1, spd: 2),
            self::Sylas     => new Stats(hp: 23, atk: 5, def: 2, spd: 2),
            self::Thorin    => new Stats(hp: 28, atk: 5, def: 2, spd: 1),
            self::Aldric    => new Stats(hp: 30, atk: 3, def: 3, spd: 1),
            self::Morgana   => new Stats(hp: 20, atk: 6, def: 1, spd: 1),
            self::Kairon    => new Stats(hp: 24, atk: 4, def: 2, spd: 2),
            self::Malak     => new Stats(hp: 22, atk: 5, def: 1, spd: 1),
            self::Elyn      => new Stats(hp: 25, atk: 3, def: 2, spd: 1),
        };
    }

    /** El jefe que lo libera al vencerlo por primera vez; null en los iniciales. */
    public function unlockedBy(): ?Boss
    {
        return match ($this) {
            self::Darius, self::Seraphina, self::Aelyn => null,
            self::Sylas   => Boss::RatQueen,
            self::Thorin  => Boss::Goblin,
            self::Aldric  => Boss::Skeleton,
            self::Morgana => Boss::Spider,
            self::Kairon  => Boss::Golem,
            self::Malak   => Boss::Lich,
            self::Elyn    => Boss::Dragon,
        };
    }

    /** Lo que cuesta en oro reclutarlo sin haber vencido a su jefe; 0 en los iniciales. */
    public function price(): int
    {
        return match ($this) {
            self::Darius, self::Seraphina, self::Aelyn => 0,
            self::Sylas   => 150,
            self::Thorin  => 300,
            self::Aldric  => 500,
            self::Morgana => 800,
            self::Kairon  => 1200,
            self::Malak   => 1700,
            self::Elyn    => 2400,
        };
    }

    public static function forBoss(Boss $boss): ?self
    {
        foreach (self::cases() as $hero) {
            if ($hero->unlockedBy() === $boss) {
                return $hero;
            }
        }

        return null;
    }
}
