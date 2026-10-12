<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\CharacterRepository;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
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
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $records   = $this->characters->battleRecords([$id])[$id] ?? [];

        return new JsonResponse(CharacterPresenter::character($character, $records));
    }
}
