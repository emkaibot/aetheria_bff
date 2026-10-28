<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Gear\Attribute;
use App\Characters\Domain\Gear\Gear;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\Gear\Slot;

/** El equipamiento tal y como sale por la API, con los textos ya puestos. */
final class GearPresenter
{
    public static function item(GearItem $i): array
    {
        $mains = $i->mainKeys();
        $attrs = [];
        foreach ($i->attributes() as $key => $value) {
            $a       = Attribute::from($key);
            $attrs[] = ['id' => $key, 'name' => $a->label(), 'unit' => $a->unit(), 'value' => $value, 'main' => in_array($key, $mains, true)];
        }

        return [
            'id'           => $i->getId(),
            'gear'         => $i->getGear()->value,
            'name'         => $i->getGear()->label(),
            'slot'         => self::slot($i->getSlot()),
            'role'         => $i->getGear()->role() === null ? null : CharacterPresenter::roleRef($i->getGear()->role()),
            'rarity'       => self::rarity($i->getRarity()),
            'level'        => $i->getLevel(),
            'upgrade'      => $i->getUpgrade(),
            'max_upgrade'  => GearItem::MAX_UPGRADE,
            'attributes'   => $attrs,
            'power'        => $i->power(),
            'equipped_by'  => $i->getEquippedBy(),
            'locked'       => $i->isLocked(),
            'sell_price'   => $i->sellPrice(),
            'upgrade_cost' => $i->upgradeCost(),
        ];
    }

    public static function slot(Slot $s): array
    {
        return ['id' => $s->value, 'name' => $s->label()];
    }

    public static function rarity(Rarity $r): array
    {
        return ['id' => $r->value, 'name' => $r->label()];
    }

    public static function piece(Gear $g): array
    {
        return [
            'id'     => $g->value,
            'name'   => $g->label(),
            'slot'   => self::slot($g->slot()),
            'role'   => $g->role() === null ? null : CharacterPresenter::roleRef($g->role()),
            'family' => $g->family(),
        ];
    }
}
