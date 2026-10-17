<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * El equipo de la tienda. Se compra con oro (que se gana venciendo jefes), una
 * vez por héroe, y mejora sus estadísticas para siempre. Es lo único en lo que
 * se gasta el oro: el éter no se compra ni se gana peleando, sólo se da a mano.
 */
enum Item: string
{
    case IronSword   = 'iron_sword';
    case ChainMail   = 'chain_mail';
    case LifeAmulet  = 'life_amulet';
    case WindBoots   = 'wind_boots';
    case ArcaneTome  = 'arcane_tome';
    case DragonAegis = 'dragon_aegis';
    case FallenCrown = 'fallen_crown';

    public function label(): string
    {
        return match ($this) {
            self::IronSword   => 'Espada de acero',
            self::ChainMail   => 'Cota de malla',
            self::LifeAmulet  => 'Amuleto vital',
            self::WindBoots   => 'Botas del viento',
            self::ArcaneTome  => 'Grimorio arcano',
            self::DragonAegis => 'Égida dracónica',
            self::FallenCrown => 'Corona del rey caído',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::IronSword   => '🗡️',
            self::ChainMail   => '🦺',
            self::LifeAmulet  => '📿',
            self::WindBoots   => '🥾',
            self::ArcaneTome  => '📕',
            self::DragonAegis => '🛡️',
            self::FallenCrown => '👑',
        };
    }

    public function price(): int
    {
        return match ($this) {
            self::IronSword   => 60,
            self::ChainMail   => 60,
            self::LifeAmulet  => 90,
            self::WindBoots   => 90,
            self::ArcaneTome  => 250,
            self::DragonAegis => 400,
            self::FallenCrown => 800,
        };
    }

    /** Multiplicadores por estadística: 1.10 = +10 %. */
    public function bonus(): array
    {
        return match ($this) {
            self::IronSword   => ['atk' => 1.10],
            self::ChainMail   => ['def' => 1.15],
            self::LifeAmulet  => ['hp' => 1.15],
            self::WindBoots   => ['spd' => 1.20],
            self::ArcaneTome  => ['atk' => 1.20],
            self::DragonAegis => ['hp' => 1.20, 'def' => 1.20],
            self::FallenCrown => ['hp' => 1.10, 'atk' => 1.10, 'def' => 1.10, 'spd' => 1.10],
        };
    }

    public function description(): string
    {
        $names = ['hp' => 'vida', 'atk' => 'ataque', 'def' => 'defensa', 'spd' => 'velocidad'];
        $parts = [];
        foreach ($this->bonus() as $stat => $mult) {
            $parts[] = sprintf('+%d%% de %s', (int) round(($mult - 1) * 100), $names[$stat]);
        }

        return ucfirst(implode(', ', $parts)) . '.';
    }
}
