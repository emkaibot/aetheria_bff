<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\EnergyLedger;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Añadir o quitar éter a mano. Body: `{amount, note?}`; `amount` con signo.
 * Devuelve el movimiento y el personaje ya actualizado (nivel, ventajas…).
 */
#[Route('/api/characters/{id}/movements', name: 'movements_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class AddMovementController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly EnergyLedger $ledger,
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $data      = JsonBody::of($request);

        if (!is_int($data['amount'] ?? null)) {
            throw new InvalidCharacter('La cantidad tiene que ser un número entero.');
        }
        $note = isset($data['note']) && is_string($data['note']) ? $data['note'] : null;

        $movement = $this->ledger->manual($character, $data['amount'], $note);

        return new JsonResponse([
            'movement'  => CharacterPresenter::movement($movement),
            'character' => CharacterPresenter::character($character, $this->characters->battleRecords([$id])[$id] ?? []),
        ], Response::HTTP_CREATED);
    }
}
