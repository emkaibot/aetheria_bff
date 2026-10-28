<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Roster;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Quiénes van en el equipo. Body: `{heroes: ["darius", …]}`, la lista
 * entera (lo que no está, sale). Devuelve la lista de héroes actualizada.
 */
#[Route('/api/characters/{id}/party', name: 'party_set', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
final class SetPartyController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Roster $roster,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $heroes    = JsonBody::of($request)['heroes'] ?? [];
        $ids       = is_array($heroes) ? array_values(array_filter($heroes, 'is_string')) : [];

        $this->roster->setParty($character, $ids);

        return new JsonResponse($this->views->heroes($character));
    }
}
