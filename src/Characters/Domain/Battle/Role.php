<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

/**
 * Cómo pelea alguien: su especial y el icono de clase que sale en su tarjeta.
 * Lo tienen los héroes del equipo y también el líder, que saca el suyo de su
 * clase (las cuatro clases del líder son los cuatro primeros papeles).
 *
 * Los ids coinciden con los iconos de `ui/classes/` de la app.
 */
enum Role: string
{
    case Warrior     = 'warrior';
    case Mage        = 'mage';
    case Rogue       = 'rogue';
    case Cleric      = 'cleric';
    case Archer      = 'archer';
    case Berserker   = 'berserker';
    case Guardian    = 'guardian';
    case Martial     = 'martial';
    case Druid       = 'druid';
    case Necromancer = 'necromancer';

    public function label(): string
    {
        return match ($this) {
            self::Warrior     => 'Guerrero',
            self::Mage        => 'Maga',
            self::Rogue       => 'Pícaro',
            self::Cleric      => 'Clériga',
            self::Archer      => 'Arquera',
            self::Berserker   => 'Berserker',
            self::Guardian    => 'Guardián',
            self::Martial     => 'Artista marcial',
            self::Druid       => 'Druida',
            self::Necromancer => 'Nigromante',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Warrior     => '⚔️',
            self::Mage        => '🔮',
            self::Rogue       => '🗡️',
            self::Cleric      => '☀️',
            self::Archer      => '🏹',
            self::Berserker   => '🪓',
            self::Guardian    => '🛡️',
            self::Martial     => '👊',
            self::Druid       => '🌿',
            self::Necromancer => '💀',
        };
    }

    public function specialName(): string
    {
        return match ($this) {
            self::Warrior     => 'Tajo brutal',
            self::Mage        => 'Bola de fuego',
            self::Rogue       => 'Doble puñalada',
            self::Cleric      => 'Luz sanadora',
            self::Archer      => 'Flecha certera',
            self::Berserker   => 'Furia sangrienta',
            self::Guardian    => 'Muro de escudos',
            self::Martial     => 'Ráfaga estelar',
            self::Druid       => 'Brote vital',
            self::Necromancer => 'Drenar alma',
        };
    }

    public function specialDescription(): string
    {
        return match ($this) {
            self::Warrior     => 'Un golpe de daño x2,6.',
            self::Mage        => 'Daño x1,7 que ignora la defensa del enemigo.',
            self::Rogue       => 'Dos golpes completos seguidos; cada uno puede ser crítico.',
            self::Cleric      => 'Cura un 12% de su vida al aliado más herido y ataca.',
            self::Archer      => 'Un disparo crítico seguro: daño x2,4.',
            self::Berserker   => 'Cuanta menos vida le queda, más pega: hasta x2,5.',
            self::Guardian    => 'Atrae los dos siguientes ataques del jefe y golpea.',
            self::Martial     => 'Tres golpes rápidos de daño x0,6.',
            self::Druid       => 'Cura un 7% de su vida a todo el equipo y golpea.',
            self::Necromancer => 'Daño x1,3 y recupera la mitad de lo que quita.',
        };
    }
}
