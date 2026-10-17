<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\BattleResult;
use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\BattleRecord;
use App\Characters\Domain\BossLocked;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Retar a un jefe: comprobar que está desbloqueado, pelear, apuntar el combate
 * y, si se gana, sumar su oro. El éter no se toca: sólo cambia a mano.
 */
final class FightBoss
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Con la fila del héroe bloqueada: el combate usa el éter y el equipo de
     * ese momento, y dos combates a la vez no se pisan el oro.
     *
     * @return array{result: BattleResult, gold: int}
     */
    public function __invoke(Character $character, Boss $boss): array
    {
        return $this->em->wrapInTransaction(function () use ($character, $boss): array {
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            $id       = $character->getId();
            $previous = $boss->previous();
            if ($previous !== null) {
                $record = $this->characters->battleRecords([$id])[$id][$previous->value] ?? null;
                if (($record['victories'] ?? 0) === 0) {
                    throw new BossLocked($boss, $previous);
                }
            }

            $items  = $this->characters->itemsFor([$id])[$id] ?? [];
            $result = (new BattleSimulator())->fight($character, $boss, $items);
            $gold   = $result->won ? $boss->gold() : 0;

            $character->earnGold($gold);
            $this->em->persist(new BattleRecord(Uuid::v7()->toRfc4122(), $character, $boss->value, $result->won, $result->rounds, $gold));

            return ['result' => $result, 'gold' => $gold];
        });
    }
}
