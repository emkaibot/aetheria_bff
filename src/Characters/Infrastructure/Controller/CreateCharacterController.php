<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Roster;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/** Body: `{name, class}`. Nace con 0 de éter y con los héroes iniciales en su equipo. */
#[Route('/api/characters', name: 'characters_create', methods: ['POST'])]
final class CreateCharacterController
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly Roster $roster,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $data  = JsonBody::of($request);
        $class = CharacterClass::tryFrom(JsonBody::string($data, 'class'))
            ?? throw new InvalidCharacter('Elige una clase.');

        $character = new Character(Uuid::v7()->toRfc4122(), $player->getId(), JsonBody::string($data, 'name'), $class);
        $this->roster->giveStarters($character);
        $this->characters->save($character);

        return new JsonResponse($this->views->one($character), Response::HTTP_CREATED);
    }
}
