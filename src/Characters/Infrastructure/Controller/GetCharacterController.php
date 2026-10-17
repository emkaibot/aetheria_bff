<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/characters/{id}', name: 'characters_get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class GetCharacterController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->one($this->owned->get($id, $player)));
    }
}
