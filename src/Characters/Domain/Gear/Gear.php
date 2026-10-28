<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

use App\Characters\Domain\Battle\Role;

/**
 * Las piezas de equipamiento que existen, cada una con su arte en la app. Lo
 * que cae es un ejemplar (GearItem) con su rareza, su nivel y sus atributos
 * tirados al azar; aquí está sólo lo común a todos los ejemplares.
 *
 * Cuatro familias, que deciden qué rarezas pueden salir y desde qué jefe
 * caen: las comunes, las armas de cada papel, las épicas y el conjunto
 * primordial del firmamento.
 */
enum Gear: string
{
    case FirmamentFists  = 'firmament_fists';
    case EmberGreatsword = 'ember_greatsword';
    case SpiralStaff     = 'spiral_staff';
    case TwinFangs       = 'twin_fangs';
    case DawnBlade       = 'dawn_blade';
    case LivingBow       = 'living_bow';
    case RuneAxe         = 'rune_axe';
    case LionShield      = 'lion_shield';
    case GroveTotem      = 'grove_totem';
    case BoneScepter     = 'bone_scepter';

    case LeatherCap  = 'leather_cap';
    case PaddedVest  = 'padded_vest';
    case ClothWraps  = 'cloth_wraps';
    case WornBoots   = 'worn_boots';
    case CopperRing  = 'copper_ring';
    case BoneCharm   = 'bone_charm';
    case OldCompass  = 'old_compass';

    case VioletHelm   = 'violet_helm';
    case NightPlate   = 'night_plate';
    case EmberGloves  = 'ember_gloves';
    case ShadowBoots  = 'shadow_boots';
    case AmethystRing = 'amethyst_ring';
    case MoonPendant  = 'moon_pendant';
    case GreenChalice = 'green_chalice';

    case FirmamentHelm   = 'firmament_helm';
    case FirmamentPlate  = 'firmament_plate';
    case FirmamentGloves = 'firmament_gloves';
    case FirmamentBoots  = 'firmament_boots';
    case FirmamentRing   = 'firmament_ring';
    case FirmamentAmulet = 'firmament_amulet';
    case FirmamentOrb    = 'firmament_orb';

    public const FAMILY_COMMON    = 'common';
    public const FAMILY_WEAPON    = 'weapon';
    public const FAMILY_EPIC      = 'epic';
    public const FAMILY_FIRMAMENT = 'firmament';

    public function label(): string
    {
        return match ($this) {
            self::FirmamentFists  => 'Puños del firmamento',
            self::EmberGreatsword => 'Mandoble de ascuas',
            self::SpiralStaff     => 'Báculo espiral',
            self::TwinFangs       => 'Colmillos gemelos',
            self::DawnBlade       => 'Hoja del alba',
            self::LivingBow       => 'Arco viviente',
            self::RuneAxe         => 'Hacha rúnica',
            self::LionShield      => 'Escudo del león',
            self::GroveTotem      => 'Tótem de la arboleda',
            self::BoneScepter     => 'Cetro de hueso',
            self::LeatherCap      => 'Gorro de cuero',
            self::PaddedVest      => 'Jubón acolchado',
            self::ClothWraps      => 'Vendas de tela',
            self::WornBoots       => 'Botas gastadas',
            self::CopperRing      => 'Anillo de cobre',
            self::BoneCharm       => 'Amuleto tallado',
            self::OldCompass      => 'Brújula vieja',
            self::VioletHelm      => 'Yelmo violeta',
            self::NightPlate      => 'Coraza nocturna',
            self::EmberGloves     => 'Guanteletes de ascuas',
            self::ShadowBoots     => 'Botas de sombra',
            self::AmethystRing    => 'Anillo de amatista',
            self::MoonPendant     => 'Colgante lunar',
            self::GreenChalice    => 'Cáliz esmeralda',
            self::FirmamentHelm   => 'Yelmo del firmamento',
            self::FirmamentPlate  => 'Coraza del firmamento',
            self::FirmamentGloves => 'Guantes del firmamento',
            self::FirmamentBoots  => 'Botas del firmamento',
            self::FirmamentRing   => 'Anillo del firmamento',
            self::FirmamentAmulet => 'Amuleto del firmamento',
            self::FirmamentOrb    => 'Orbe del firmamento',
        };
    }

    public function slot(): Slot
    {
        return match ($this) {
            self::LeatherCap, self::VioletHelm, self::FirmamentHelm       => Slot::Head,
            self::PaddedVest, self::NightPlate, self::FirmamentPlate      => Slot::Chest,
            self::ClothWraps, self::EmberGloves, self::FirmamentGloves    => Slot::Hands,
            self::WornBoots, self::ShadowBoots, self::FirmamentBoots      => Slot::Boots,
            self::CopperRing, self::AmethystRing, self::FirmamentRing     => Slot::Ring,
            self::BoneCharm, self::MoonPendant, self::FirmamentAmulet     => Slot::Amulet,
            self::OldCompass, self::GreenChalice, self::FirmamentOrb      => Slot::Relic,
            default                                                       => Slot::Weapon,
        };
    }

    /** El único papel que puede llevar un arma; null en el resto, que vale para todos. */
    public function role(): ?Role
    {
        return match ($this) {
            self::FirmamentFists  => Role::Martial,
            self::EmberGreatsword => Role::Warrior,
            self::SpiralStaff     => Role::Mage,
            self::TwinFangs       => Role::Rogue,
            self::DawnBlade       => Role::Cleric,
            self::LivingBow       => Role::Archer,
            self::RuneAxe         => Role::Berserker,
            self::LionShield      => Role::Guardian,
            self::GroveTotem      => Role::Druid,
            self::BoneScepter     => Role::Necromancer,
            default               => null,
        };
    }

    public function family(): string
    {
        if ($this === self::FirmamentFists || str_starts_with($this->value, 'firmament_')) {
            return self::FAMILY_FIRMAMENT;
        }
        if ($this->slot() === Slot::Weapon) {
            return self::FAMILY_WEAPON;
        }

        return in_array($this, [self::LeatherCap, self::PaddedVest, self::ClothWraps, self::WornBoots, self::CopperRing, self::BoneCharm, self::OldCompass], true)
            ? self::FAMILY_COMMON
            : self::FAMILY_EPIC;
    }

    /** @return array<string, int> rareza => peso, para tirar la de un ejemplar */
    public function rarityWeights(): array
    {
        return match ($this->family()) {
            self::FAMILY_COMMON    => [Rarity::Common->value => 70, Rarity::Uncommon->value => 30],
            self::FAMILY_WEAPON    => [Rarity::Uncommon->value => 30, Rarity::Rare->value => 40, Rarity::Epic->value => 25, Rarity::Legendary->value => 5],
            self::FAMILY_EPIC      => [Rarity::Rare->value => 50, Rarity::Epic->value => 40, Rarity::Legendary->value => 10],
            self::FAMILY_FIRMAMENT => [Rarity::Mythic->value => 70, Rarity::Primordial->value => 30],
        };
    }

    /**
     * Los atributos principales de la ranura en un objeto de nivel `$level`,
     * antes de la rareza.
     *
     * @return array<string, float> atributo => valor
     */
    public function mains(int $level): array
    {
        return match ($this->slot()) {
            Slot::Weapon => [Attribute::Atk->value => 2 + 0.8 * $level],
            Slot::Head   => [Attribute::Def->value => 1 + 0.4 * $level, Attribute::Hp->value => 6 + 3 * $level],
            Slot::Chest  => [Attribute::Hp->value => 10 + 5 * $level, Attribute::Def->value => 1 + 0.5 * $level],
            Slot::Hands  => [Attribute::Atk->value => 1 + 0.4 * $level, Attribute::Crit->value => 2],
            Slot::Boots  => [Attribute::Spd->value => 1 + 0.15 * $level, Attribute::Def->value => 0.5 + 0.2 * $level],
            Slot::Ring   => [Attribute::Atk->value => 1 + 0.3 * $level, Attribute::CritDamage->value => 8],
            Slot::Amulet => [Attribute::Hp->value => 8 + 4 * $level, Attribute::Skill->value => 4],
            Slot::Relic  => [Attribute::Hp->value => 5 + 2.5 * $level, Attribute::Atk->value => 1 + 0.3 * $level, Attribute::Def->value => 1 + 0.3 * $level],
        };
    }

    /** @return self[] */
    public static function ofFamily(string $family): array
    {
        return array_values(array_filter(self::cases(), fn (self $g) => $g->family() === $family));
    }
}
