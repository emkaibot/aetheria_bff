<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\Combatant;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Loadout;
use App\Characters\Domain\Pets\CharacterPet;

/**
 * Quién pelea por un personaje y con qué: el líder y los héroes de su equipo
 * con lo que llevan puesto, y sus mascotas activas.
 */
final class Lineup
{
    /**
     * @param Combatant[]     $combatants el líder primero
     * @param CharacterHero[] $party
     * @param CharacterPet[]  $pets       las activas
     * @param CharacterHero[] $heroes     toda la compañía
     * @param GearItem[]      $gear       todo el inventario
     */
    private function __construct(
        public readonly array $combatants,
        public readonly array $party,
        public readonly array $pets,
        public readonly array $heroes,
        public readonly array $gear,
    ) {}

    public static function of(Character $c, CharacterRepository $characters): self
    {
        $id     = $c->getId();
        $heroes = $characters->heroesFor([$id])[$id] ?? [];
        $gear   = $characters->gearFor([$id])[$id] ?? [];
        $worn   = Loadout::byWearer($gear);
        $party  = array_values(array_filter($heroes, fn (CharacterHero $h) => $h->isInParty()));
        $pets   = array_values(array_filter($characters->petsFor([$id])[$id] ?? [], fn (CharacterPet $p) => $p->isActive()));

        $leaderGear = $worn['leader'] ?? [];
        $combatants = [Combatant::leader($c->getName(), $c->getClass(), $c->getEnergy(), $characters->itemsFor([$id])[$id] ?? [], Loadout::stats($leaderGear), Loadout::bonus($leaderGear))];
        foreach ($party as $h) {
            $mine         = $worn[$h->getHero()->value] ?? [];
            $combatants[] = Combatant::hero($h->getHero(), $h->getExp(), Loadout::stats($mine), Loadout::bonus($mine));
        }

        return new self($combatants, $party, $pets, $heroes, $gear);
    }

    /** @return array<int, array{0: \App\Characters\Domain\Pets\Pet, 1: int}> para el simulador */
    public function petLevels(): array
    {
        return array_map(fn (CharacterPet $p) => [$p->getPet(), $p->level()], $this->pets);
    }
}
