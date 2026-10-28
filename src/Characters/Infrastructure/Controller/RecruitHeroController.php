<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Roster;
use App\Characters\Domain\Hero;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Reclutar a un héroe con oro. Body: `{hero}`. Devuelve la lista de héroes actualizada. */
#[Route('/api/characters/{id}/heroes', name: 'heroes_recruit', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class RecruitHeroController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Roster $roster,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $hero      = Hero::tryFrom(JsonBody::string(JsonBody::of($request), 'hero'))
            ?? throw new InvalidCharacter('Ese héroe no existe.');

        $this->roster->recruit($character, $hero);

        return new JsonResponse($this->views->heroes($character), Response::HTTP_CREATED);
    }
}
