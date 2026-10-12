<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Controller;

use App\Characters\Domain\CharacterRepository;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** La cuenta de la sesión y su resumen (cuántos personajes, cuánto éter entre todos). */
#[Route('/api/me', name: 'me', methods: ['GET'])]
final class MeController
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function __invoke(#[CurrentUser] Player $player): JsonResponse
    {
        $summary = $this->characters->summaryForOwner($player->getId());

        return new JsonResponse([
            'id'               => $player->getId(),
            'email'            => $player->getEmail(),
            'name'             => $player->getName(),
            'character_count'  => $summary['count'],
            'total_energy'     => $summary['energy'],
        ]);
    }
}
