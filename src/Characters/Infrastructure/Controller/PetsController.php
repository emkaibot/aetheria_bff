<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Kennel;
use App\Characters\Domain\InvalidPets;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Las mascotas de un personaje: verlas, comprar una con oro (`{pet}`) y
 * elegir las que van con el equipo (`{pets: [...]}`, la lista entera).
 */
final class PetsController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Kennel $kennel,
        private readonly CharacterViews $views,
    ) {}

    #[Route('/api/characters/{id}/pets', name: 'pets_list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function list(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->pets($this->owned->get($id, $player)));
    }

    #[Route('/api/characters/{id}/pets', name: 'pets_buy', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function buy(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $pet       = Pet::tryFrom(JsonBody::string(JsonBody::of($request), 'pet'))
            ?? throw new InvalidPets('Esa mascota no existe.');
        $this->kennel->buy($character, $pet);

        return new JsonResponse($this->views->pets($character), Response::HTTP_CREATED);
    }

    #[Route('/api/characters/{id}/pets/active', name: 'pets_active', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function active(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $pets      = JsonBody::of($request)['pets'] ?? [];
        $this->kennel->setActive($character, is_array($pets) ? array_values(array_filter($pets, 'is_string')) : []);

        return new JsonResponse($this->views->pets($character));
    }
}
