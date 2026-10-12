<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Body: `{name?, class?}`. El éter **no** se toca aquí: va por movimientos,
 * para que quede en el histórico.
 */
#[Route('/api/characters/{id}', name: 'characters_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
final class UpdateCharacterController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $data      = JsonBody::of($request);

        if (array_key_exists('name', $data)) {
            $character->rename(JsonBody::string($data, 'name'));
        }
        if (array_key_exists('class', $data)) {
            $character->changeClass(
                CharacterClass::tryFrom(JsonBody::string($data, 'class')) ?? throw new InvalidCharacter('Esa clase no existe.'),
            );
        }

        $this->characters->save($character);

        return new JsonResponse(CharacterPresenter::character($character, $this->characters->battleRecords([$id])[$id] ?? []));
    }
}
