<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Las clases. Cada una parte de unas estadísticas y crece distinto por nivel,
 * y tiene un movimiento especial que usa cada tres turnos en combate.
 */
enum CharacterClass: string
{
    case Warrior = 'warrior';
    case Mage    = 'mage';
    case Rogue   = 'rogue';
    case Cleric  = 'cleric';

    public function label(): string
    {
        return match ($this) {
            self::Warrior => 'Guerrero',
            self::Mage    => 'Maga',
            self::Rogue   => 'Pícaro',
            self::Cleric  => 'Clériga',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Warrior => '🛡️',
            self::Mage    => '🔮',
            self::Rogue   => '🗡️',
            self::Cleric  => '✨',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Warrior => 'Aguanta lo que le echen y pega fuerte. La opción segura.',
            self::Mage    => 'Frágil, pero su magia atraviesa cualquier armadura.',
            self::Rogue   => 'El más rápido: ataca primero y a menudo dos veces.',
            self::Cleric  => 'Se cura en mitad del combate. Gana por desgaste.',
        };
    }

    public function base(): Stats
    {
        return match ($this) {
            self::Warrior => new Stats(hp: 130, atk: 14, def: 12, spd: 6),
            self::Mage    => new Stats(hp: 90,  atk: 19, def: 5,  spd: 8),
            self::Rogue   => new Stats(hp: 100, atk: 16, def: 7,  spd: 13),
            self::Cleric  => new Stats(hp: 115, atk: 12, def: 10, spd: 7),
        };
    }

    /** Lo que gana por cada nivel por encima del 1. */
    public function growth(): Stats
    {
        return match ($this) {
            self::Warrior => new Stats(hp: 30, atk: 3, def: 3, spd: 1),
            self::Mage    => new Stats(hp: 20, atk: 6, def: 1, spd: 1),
            self::Rogue   => new Stats(hp: 23, atk: 5, def: 2, spd: 2),
            self::Cleric  => new Stats(hp: 26, atk: 3, def: 2, spd: 1),
        };
    }

    public function specialName(): string
    {
        return match ($this) {
            self::Warrior => 'Tajo brutal',
            self::Mage    => 'Bola de fuego',
            self::Rogue   => 'Doble puñalada',
            self::Cleric  => 'Luz sanadora',
        };
    }

    public function specialDescription(): string
    {
        return match ($this) {
            self::Warrior => 'Golpe que hace el doble de daño.',
            self::Mage    => 'Daño x1,7 que ignora la defensa del enemigo.',
            self::Rogue   => 'Dos golpes completos seguidos; cada uno puede ser crítico.',
            self::Cleric  => 'Recupera un 14% de la vida y ataca a la vez.',
        };
    }
}
