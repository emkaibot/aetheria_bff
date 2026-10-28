<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\BattleResult;
use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\BattleRecord;
use App\Characters\Domain\BossLocked;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Hero;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Retar a un jefe: comprobar que está desbloqueado, pelear con el equipo,
 * apuntar el combate y repartir lo ganado: oro si se gana, EXP para los
 * héroes del equipo (un tercio si se pierde) y, la primera vez que cae el
 * jefe, el héroe que guardaba. El éter no se toca: sólo cambia a mano.
 */
final class FightBoss
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly Roster $roster,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Con la fila del personaje bloqueada: el combate usa el éter, el equipo
     * y los héroes de ese momento, y dos combates a la vez no se pisan.
     *
     * @return array{result: BattleResult, gold: int, exp: int, level_ups: array<string, int>, unlocked: ?Hero}
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

            $items  = $this->characters->itemsFor([$id])[$id] ?? [];
            $heroes = $this->characters->heroesFor([$id])[$id] ?? [];
            $party  = array_values(array_filter($heroes, fn (CharacterHero $h) => $h->isInParty()));

            $result = (new BattleSimulator())->fight($character, $boss, $items, $party);
            $gold   = $result->won ? $boss->gold() : 0;
            $exp    = $result->won ? $boss->exp() : intdiv($boss->exp(), 3);

            $levelUps = [];
            foreach ($party as $h) {
                $up = $h->gainExp($exp);
                if ($up > 0) {
                    $levelUps[$h->getHero()->value] = $up;
                }
            }

            // El héroe que guarda el jefe, la primera vez que cae (si no se
            // había reclutado ya con oro).
            $unlocked = null;
            $guarded  = Hero::forBoss($boss);
            $owned    = array_map(fn (CharacterHero $h) => $h->getHero(), $heroes);
            if ($result->won && $guarded !== null && !in_array($guarded, $owned, true)) {
                $this->roster->add($character, $guarded);
                $unlocked = $guarded;
            }

            $character->earnGold($gold);
            $this->em->persist(new BattleRecord(Uuid::v7()->toRfc4122(), $character, $boss->value, $result->won, $result->rounds, $gold));

            return ['result' => $result, 'gold' => $gold, 'exp' => $party === [] ? 0 : $exp, 'level_ups' => $levelUps, 'unlocked' => $unlocked];
        });
    }
}
