<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Inventory;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Lo que se le hace a un objeto del inventario: `equip` (body `{wearer}`:
 * `leader` o el id de un héroe), `unequip`, `lock`, `unlock`, `upgrade`
 * (con oro) y `sell`. Devuelve el inventario actualizado.
 */
#[Route(
    '/api/characters/{id}/gear/{itemId}/{action}',
    name: 'gear_action',
    requirements: ['id' => Requirement::UUID, 'itemId' => Requirement::UUID, 'action' => 'equip|unequip|lock|unlock|upgrade|sell'],
    methods: ['POST'],
)]
final class GearActionController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterRepository $characters,
        private readonly Inventory $inventory,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, string $itemId, string $action, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $item      = $this->characters->findGear($character->getId(), $itemId)
            ?? throw new NotFoundHttpException('item_not_found');

        match ($action) {
            'equip'   => $this->inventory->equip($character, $item, JsonBody::string(JsonBody::of($request), 'wearer')),
            'unequip' => $this->inventory->unequip($character, $item),
            'lock'    => $this->inventory->setLocked($character, $item, true),
            'unlock'  => $this->inventory->setLocked($character, $item, false),
            'upgrade' => $this->inventory->upgrade($character, $item),
            'sell'    => $this->inventory->sell($character, [$item]),
        };

        return new JsonResponse($this->views->gear($character));
    }
}
