<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\BattleResult;
use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\BattleRecord;
use App\Characters\Domain\BossLocked;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Gear\GearForge;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Hero;
use App\Characters\Domain\Pets\CharacterPet;
use App\Characters\Domain\Pets\Pet;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Retar a un jefe: comprobar que está desbloqueado, pelear con el equipo,
 * apuntar el combate y repartir lo ganado. Si se gana: oro, uno o dos
 * objetos y, la primera vez que cae el jefe, el héroe y la mascota que
 * guardaba. Gane o pierda, EXP para los héroes y las mascotas que pelearon
 * (un tercio si se pierde). El éter no se toca: sólo cambia a mano.
 */
final class FightBoss
{
    /** Probabilidad de un segundo objeto al vencer. */
    private const SECOND_DROP = 0.25;

    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly Roster $roster,
        private readonly Kennel $kennel,
        private readonly Inventory $inventory,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Con la fila del personaje bloqueada: el combate usa el éter, el equipo
     * y los objetos de ese momento, y dos combates a la vez no se pisan.
     *
     * @return array{result: BattleResult, gold: int, exp: int, level_ups: array<string, int>, evolutions: array<string, int>, unlocked: ?Hero, unlocked_pet: ?Pet, drops: GearItem[], sold: int}
     */
    public function __invoke(Character $character, Boss $boss): array
    {
        return $this->em->wrapInTransaction(function () use ($character, $boss): array {
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            $id      = $character->getId();
            $records = $this->characters->battleRecords([$id])[$id] ?? [];

            $previous = $boss->previous();
            if ($previous !== null && ($records[$previous->value]['victories'] ?? 0) === 0) {
                throw new BossLocked($boss, $previous);
            }

            $lineup = Lineup::of($character, $this->characters);
            $result = (new BattleSimulator())->run($lineup->combatants, $boss, $lineup->petLevels());
            $gold   = $result->won ? $boss->gold() : 0;
            $exp    = $result->won ? $boss->exp() : intdiv($boss->exp(), 3);
            $gained = Experience::grant($lineup->party, $lineup->pets, $exp);

            // Lo que guardaba el jefe, la primera vez que cae (si no se había
            // comprado ya con oro).
            $unlocked = $unlockedPet = null;
            if ($result->won) {
                $hero = Hero::forBoss($boss);
                if ($hero !== null && !in_array($hero, array_map(fn (CharacterHero $h) => $h->getHero(), $lineup->heroes), true)) {
                    $this->roster->add($character, $hero);
                    $unlocked = $hero;
                }
                $pet   = Pet::forBoss($boss);
                $owned = array_map(fn (CharacterPet $p) => $p->getPet(), $this->characters->petsFor([$id])[$id] ?? []);
                if ($pet !== null && !in_array($pet, $owned, true)) {
                    $this->kennel->add($character, $pet);
                    $unlockedPet = $pet;
                }
            }

            $drops = ['kept' => [], 'sold' => 0, 'gold' => 0];
            if ($result->won) {
                $forge = new GearForge(new Rng());
                $tier  = array_search($boss, Boss::cases(), true);
                $loot  = [$forge->drop($character, $boss->recommendedLevel(), $tier)];
                if ((new Rng())->chance(self::SECOND_DROP)) {
                    $loot[] = $forge->drop($character, $boss->recommendedLevel(), $tier);
                }
                $drops = $this->inventory->store($character, $loot);
            }

            $character->earnGold($gold);
            $this->em->persist(new BattleRecord(Uuid::v7()->toRfc4122(), $character, $boss->value, $result->won, $result->rounds, $gold));

            return [
                'result'       => $result,
                'gold'         => $gold + $drops['gold'],
                'exp'          => $lineup->party === [] && $lineup->pets === [] ? 0 : $exp,
                'level_ups'    => $gained['level_ups'],
                'evolutions'   => $gained['evolutions'],
                'unlocked'     => $unlocked,
                'unlocked_pet' => $unlockedPet,
                'drops'        => $drops['kept'],
                'sold'         => $drops['sold'],
            ];
        });
    }
}
