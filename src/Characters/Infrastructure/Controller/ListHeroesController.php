<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Los diez héroes vistos desde un personaje: los que tiene, su equipo y cómo conseguir el resto. */
#[Route('/api/characters/{id}/heroes', name: 'heroes_list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ListHeroesController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->heroes($this->owned->get($id, $player)));
    }
}
