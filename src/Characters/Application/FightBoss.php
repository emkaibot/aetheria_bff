<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\BattleResult;
use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\BossLocked;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Domain\MovementKind;
use App\Characters\Domain\Progression;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retar a un jefe: comprobar que está desbloqueado, pelear y apuntar lo que se
 * gana o se pierde en el histórico como un movimiento más.
 */
final class FightBoss
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EnergyLedger $ledger,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Todo dentro de una transacción con la fila del personaje bloqueada: el
     * combate se simula con el éter **de después del bloqueo**. Si se leyera
     * antes, un «quitar éter» simultáneo dejaría pelear con ventajas que el
     * saldo nuevo ya no da. `record` reutiliza esta misma transacción.
     *
     * @return array{result: BattleResult, movement: EnergyMovement, levelBefore: int}
     */
    public function __invoke(Character $character, Boss $boss): array
    {
        return $this->em->wrapInTransaction(function () use ($character, $boss): array {
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            return $this->fight($character, $boss);
        });
    }

    /** @return array{result: BattleResult, movement: EnergyMovement, levelBefore: int} */
    private function fight(Character $character, Boss $boss): array
    {
        $levelBefore = Progression::level($character->getEnergy());

        $previous = $boss->previous();
        if ($previous !== null) {
            $record = $this->characters->battleRecords([$character->getId()])[$character->getId()][$previous->value] ?? null;
            if (($record['victories'] ?? 0) === 0) {
                throw new BossLocked($boss, $previous);
            }
        }

        $result = (new BattleSimulator())->fight($character, $boss);

        $movement = $result->won
            ? $this->ledger->record($character, $boss->reward(), MovementKind::Victory, sprintf('Victoria contra %s', $boss->name()), $boss->value)
            : $this->ledger->record($character, -$boss->penalty(), MovementKind::Defeat, sprintf('Derrota contra %s', $boss->name()), $boss->value, clampToBalance: true);

        return ['result' => $result, 'movement' => $movement, 'levelBefore' => $levelBefore];
    }
}
