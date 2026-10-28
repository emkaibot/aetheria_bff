<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Application\Expeditions;
use App\Characters\Application\Inventory;
use App\Characters\Application\Lineup;
use App\Characters\Domain\Battle\Combatant;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Expeditions\Difficulty;
use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Expeditions\Rates;
use App\Characters\Domain\Expeditions\Zone;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Loadout;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\Gear\Slot;
use App\Characters\Domain\Hero;
use App\Characters\Domain\InvalidGear;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Domain\Progression;

/**
 * La ficha de uno o varios personajes tal y como sale por la API: con su récord
 * de combates, su equipo y su compañía de héroes, que viven en otras tablas.
 * En una consulta por tabla aunque sean muchos.
 */
final class CharacterViews
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function one(Character $c): array
    {
        return $this->many([$c])[0];
    }

    /** @param Character[] $list */
    public function many(array $list): array
    {
        $ids     = array_map(fn (Character $c) => $c->getId(), $list);
        $records = $this->characters->battleRecords($ids);
        $items   = $this->characters->itemsFor($ids);
        $heroes  = $this->characters->heroesFor($ids);
        $pets    = $this->characters->petsFor($ids);
        $gear    = $this->characters->gearFor($ids);

        return array_map(
            fn (Character $c) => CharacterPresenter::character(
                $c,
                $records[$c->getId()] ?? [],
                $items[$c->getId()] ?? [],
                $heroes[$c->getId()] ?? [],
                $pets[$c->getId()] ?? [],
                count($gear[$c->getId()] ?? []),
            ),
            $list,
        );
    }

    /**
     * Los diez héroes vistos desde un personaje: los que tiene y los que le
     * faltan, en el orden del catálogo. Con su oro, para la pantalla de reclutar.
     */
    public function heroes(Character $c): array
    {
        $owned = [];
        foreach ($this->characters->heroesFor([$c->getId()])[$c->getId()] ?? [] as $h) {
            $owned[$h->getHero()->value] = $h;
        }

        return [
            'items'     => array_map(fn (Hero $h) => CharacterPresenter::heroFor($h, $owned[$h->value] ?? null), Hero::cases()),
            'party_max' => CharacterHero::PARTY_MAX,
            'gold'      => $c->getGold(),
        ];
    }

    /** El inventario de un personaje, con su oro. */
    public function gear(Character $c): array
    {
        return [
            'items'    => array_map(fn (GearItem $i) => GearPresenter::item($i), $this->characters->gearFor([$c->getId()])[$c->getId()] ?? []),
            'capacity' => Inventory::CAPACITY,
            'gold'     => $c->getGold(),
        ];
    }

    /**
     * Lo que lleva puesto el líder (`leader`) o un héroe, ranura a ranura, y
     * cómo le quedan las estadísticas con ello.
     */
    public function loadout(Character $c, string $wearer): array
    {
        $lineup = Lineup::of($c, $this->characters);
        $worn   = Loadout::byWearer($lineup->gear)[$wearer] ?? [];

        if ($wearer === 'leader') {
            $who       = ['key' => 'leader', 'name' => $c->getName(), 'hero' => null, 'role' => CharacterPresenter::roleRef($c->getClass()->role()), 'level' => Progression::level($c->getEnergy()), 'class' => $c->getClass()->value];
            $combatant = Combatant::leader($c->getName(), $c->getClass(), $c->getEnergy(), $this->characters->itemsFor([$c->getId()])[$c->getId()] ?? [], Loadout::stats($worn), Loadout::bonus($worn));
        } else {
            $owned = null;
            foreach ($lineup->heroes as $h) {
                if ($h->getHero()->value === $wearer) {
                    $owned = $h;
                }
            }
            if ($owned === null) {
                throw new InvalidGear('Ese héroe no está en tu compañía.');
            }
            $who       = ['key' => $wearer, 'name' => $owned->getHero()->label(), 'hero' => $wearer, 'role' => CharacterPresenter::roleRef($owned->getHero()->role()), 'level' => $owned->level(), 'class' => null];
            $combatant = Combatant::hero($owned->getHero(), $owned->getExp(), Loadout::stats($worn), Loadout::bonus($worn));
        }

        $slots = [];
        foreach (Slot::cases() as $slot) {
            $slots[$slot->value] = null;
        }
        foreach ($worn as $item) {
            $slots[$item->getSlot()->value] = GearPresenter::item($item);
        }

        // A quién se le puede poner algo: el líder y toda la compañía.
        $wearers = [['key' => 'leader', 'name' => $c->getName(), 'hero' => null, 'role' => $c->getClass()->role()->value]];
        foreach ($lineup->heroes as $h) {
            $wearers[] = ['key' => $h->getHero()->value, 'name' => $h->getHero()->label(), 'hero' => $h->getHero()->value, 'role' => $h->getHero()->role()->value];
        }

        $bonus = $combatant->bonus;

        return [
            'wearer'  => $who,
            'stats'   => $combatant->stats->toArray(),
            'bonus'   => [
                'crit'        => round($bonus->crit * 100, 1),
                'crit_damage' => round($bonus->critDamage * 100, 1),
                'lifesteal'   => round($bonus->lifesteal * 100, 1),
                'healing'     => round($bonus->healing * 100, 1),
                'dodge'       => round($bonus->dodge * 100, 1),
                'skill'       => round($bonus->skill * 100, 1),
            ],
            'slots'   => $slots,
            'wearers' => $wearers,
        ];
    }

    /** Las seis mascotas vistas desde un personaje. */
    public function pets(Character $c): array
    {
        $owned = [];
        foreach ($this->characters->petsFor([$c->getId()])[$c->getId()] ?? [] as $p) {
            $owned[$p->getPet()->value] = $p;
        }

        return [
            'items'      => array_map(fn (Pet $p) => PetPresenter::petFor($p, $owned[$p->value] ?? null), Pet::cases()),
            'active_max' => Pet::ACTIVE_MAX,
            'gold'       => $c->getGold(),
        ];
    }

    /** La expedición en marcha (o ninguna), las zonas con lo que rendiría cada dificultad y el equipo que iría. */
    public function expedition(Character $c, Expeditions $expeditions): array
    {
        $now    = new \DateTimeImmutable();
        $active = $this->characters->activeExpedition($c->getId());
        $open   = $expeditions->openZones($c);
        $lineup = Lineup::of($c, $this->characters);
        $level  = Progression::teamLevel($c->getEnergy(), $lineup->party);

        $zones = [];
        foreach (Zone::cases() as $zone) {
            $rates = [];
            foreach (Difficulty::cases() as $d) {
                $rates[$d->value] = Rates::for($zone, $d, $level)->toArray();
            }
            $zones[] = ExpeditionPresenter::zone($zone) + ['open' => in_array($zone, $open, true), 'rates' => $rates];
        }

        return [
            'active'       => $active === null ? null : ExpeditionPresenter::expedition($active, $now),
            'zones'        => $zones,
            'difficulties' => array_map(fn (Difficulty $d) => ExpeditionPresenter::difficulty($d), Difficulty::cases()),
            'limits'       => Expedition::LIMITS,
            'rarities'     => array_map(fn (Rarity $r) => GearPresenter::rarity($r), Rarity::cases()),
            'team_level'   => $level,
            'party'        => array_map(fn ($h) => ['id' => $h->getHero()->value, 'name' => $h->getHero()->label(), 'role' => $h->getHero()->role()->value, 'level' => $h->level()], $lineup->party),
            'pets'         => PetPresenter::summary($lineup->pets),
            'leader'       => ['name' => $c->getName(), 'class' => $c->getClass()->value, 'level' => Progression::level($c->getEnergy())],
            'gold'         => $c->getGold(),
        ];
    }
}
