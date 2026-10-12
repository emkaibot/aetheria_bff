<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Las ventajas que da el éter acumulado. Se tienen mientras el saldo esté por
 * encima del umbral: si se gasta o se pierde éter, se pierden también. Es lo
 * que hace que el saldo importe y no sea sólo un marcador.
 */
enum Perk: string
{
    case Spark      = 'spark';
    case StoneSkin  = 'stoneskin';
    case Reflexes   = 'reflexes';
    case Critical   = 'critical';
    case SecondWind = 'second_wind';
    case Vampiric   = 'vampiric';
    case Aura       = 'aura';
    case Ascension  = 'ascension';

    public function threshold(): int
    {
        return match ($this) {
            self::Spark      => 25,
            self::StoneSkin  => 75,
            self::Reflexes   => 150,
            self::Critical   => 300,
            self::SecondWind => 500,
            self::Vampiric   => 800,
            self::Aura       => 1200,
            self::Ascension  => 2000,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Spark      => 'Chispa de éter',
            self::StoneSkin  => 'Piel de piedra',
            self::Reflexes   => 'Reflejos felinos',
            self::Critical   => 'Golpe certero',
            self::SecondWind => 'Segundo aliento',
            self::Vampiric   => 'Sed vampírica',
            self::Aura       => 'Aura de éter',
            self::Ascension  => 'Ascensión',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Spark      => '⚡',
            self::StoneSkin  => '🪨',
            self::Reflexes   => '🐈',
            self::Critical   => '🎯',
            self::SecondWind => '💨',
            self::Vampiric   => '🩸',
            self::Aura       => '🌟',
            self::Ascension  => '👑',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Spark      => '+8% de ataque.',
            self::StoneSkin  => 'Recibes un 10% menos de daño.',
            self::Reflexes   => '10% de probabilidad de esquivar un golpe.',
            self::Critical   => '15% de probabilidad de golpe crítico (daño doble).',
            self::SecondWind => 'Una vez por combate, al bajar del 25% de vida recuperas un 35%.',
            self::Vampiric   => 'Te curas un 12% del daño que haces.',
            self::Aura       => '+15% a todas las estadísticas.',
            self::Ascension  => 'Tu primer golpe de cada combate hace el triple de daño.',
        };
    }

    /** @return Perk[] las que da este saldo, en orden de umbral */
    public static function unlockedBy(int $energy): array
    {
        return array_values(array_filter(self::cases(), fn (Perk $p) => $energy >= $p->threshold()));
    }

    public static function nextFor(int $energy): ?Perk
    {
        foreach (self::cases() as $perk) {
            if ($energy < $perk->threshold()) {
                return $perk;
            }
        }

        return null;
    }
}
