<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** El inventario de un personaje, del objeto más nuevo al más viejo. */
#[Route('/api/characters/{id}/gear', name: 'gear_list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ListGearController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->gear($this->owned->get($id, $player)));
    }
}
