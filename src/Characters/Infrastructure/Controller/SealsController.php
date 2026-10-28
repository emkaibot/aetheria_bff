<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Seals;
use App\Characters\Domain\Hero;
use App\Characters\Domain\InvalidParty;
use App\Characters\Domain\InvalidPets;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Characters\Infrastructure\Presenter\GearPresenter;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Gastar sellos de incursión: una estrella para un héroe, EXP para una mascota o el cofre primordial. */
final class SealsController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Seals $seals,
        private readonly CharacterViews $views,
    ) {}

    #[Route('/api/characters/{id}/heroes/{hero}/star', name: 'seals_star', requirements: ['id' => Requirement::UUID, 'hero' => '[a-z]+'], methods: ['POST'])]
    public function star(string $id, string $hero, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $this->seals->star($character, Hero::tryFrom($hero) ?? throw new InvalidParty('Ese héroe no existe.'));

        return new JsonResponse($this->views->heroes($character));
    }

    #[Route('/api/characters/{id}/pets/{pet}/feed', name: 'seals_feed', requirements: ['id' => Requirement::UUID, 'pet' => '[a-z]+'], methods: ['POST'])]
    public function feed(string $id, string $pet, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $evolved   = $this->seals->feed($character, Pet::tryFrom($pet) ?? throw new InvalidPets('Esa mascota no existe.'));

        return new JsonResponse(['evolved' => $evolved > 0] + $this->views->pets($character));
    }

    #[Route('/api/characters/{id}/seals/chest', name: 'seals_chest', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function chest(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $item      = $this->seals->chest($character);

        return new JsonResponse(['item' => GearPresenter::item($item), 'seals' => $character->getSeals()]);
    }
}
