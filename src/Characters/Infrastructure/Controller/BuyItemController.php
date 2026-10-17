<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\BuyItem;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Domain\Item;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Comprar un objeto de la tienda. Body: `{item}`. Devuelve el héroe ya equipado. */
#[Route('/api/characters/{id}/items', name: 'items_buy', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class BuyItemController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly BuyItem $buy,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $item      = Item::tryFrom(JsonBody::string(JsonBody::of($request), 'item'))
            ?? throw new InvalidCharacter('Ese objeto no existe.');

        ($this->buy)($character, $item);

        return new JsonResponse($this->views->one($character), Response::HTTP_CREATED);
    }
}
