<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\CharacterRepository;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/characters', name: 'characters_list', methods: ['GET'])]
final class ListCharactersController
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(#[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse([
            'items' => $this->views->many($this->characters->listByOwner($player->getId())),
        ]);
    }
}
