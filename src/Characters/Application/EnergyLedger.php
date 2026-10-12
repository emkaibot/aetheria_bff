<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Character;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Domain\MovementKind;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * La única puerta para cambiar el éter de un personaje.
 *
 * Saldo y movimiento se escriben en la misma transacción y con la fila del
 * personaje bloqueada (`SELECT … FOR UPDATE`): dos peticiones a la vez —un
 * doble toque en «+50», o un combate mientras se resta a mano— se ponen en
 * fila en vez de leer el mismo saldo y pisarse.
 */
final class EnergyLedger
{
    /** Tope por movimiento manual, en los dos sentidos: un dedo de más no es un millón. */
    public const MANUAL_MAX = 100000;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function manual(Character $character, int $amount, ?string $note): EnergyMovement
    {
        if ($amount === 0 || abs($amount) > self::MANUAL_MAX) {
            throw new InvalidCharacter(sprintf('La cantidad debe estar entre 1 y %d (con signo).', self::MANUAL_MAX));
        }

        return $this->record($character, $amount, MovementKind::Manual, $note);
    }

    /**
     * Apunta un movimiento. Si `$clampToBalance`, una resta mayor que el saldo
     * se queda en el saldo (lo que se pierde en un combate nunca deja en
     * negativo); si no, falla con InsufficientEnergy.
     */
    public function record(
        Character $character,
        int $amount,
        MovementKind $kind,
        ?string $note = null,
        ?string $bossId = null,
        bool $clampToBalance = false,
    ): EnergyMovement {
        return $this->em->wrapInTransaction(function () use ($character, $amount, $kind, $note, $bossId, $clampToBalance): EnergyMovement {
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            // El lock relee la fila, pero no repuebla la entidad: sin esto
            // aplicaríamos el delta al saldo que se leyó antes de esperar.
            $this->em->refresh($character);

            if ($clampToBalance && $amount < 0) {
                $amount = -min(-$amount, $character->getEnergy());
            }

            $balance  = $amount === 0 ? $character->getEnergy() : $character->applyEnergy($amount);
            $movement = new EnergyMovement(Uuid::v7()->toRfc4122(), $character, $amount, $balance, $kind, $note, $bossId);

            $this->em->persist($movement);

            return $movement;
        });
    }
}
