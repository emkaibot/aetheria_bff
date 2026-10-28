<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Inventory;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Lo que lleva puesto el líder (`leader`) o un héroe y sus estadísticas con
 * ello. Con POST a `…/auto`, antes le pone lo mejor que haya libre.
 */
final class LoadoutController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Inventory $inventory,
        private readonly CharacterViews $views,
    ) {}

    #[Route('/api/characters/{id}/loadout/{wearer}', name: 'loadout_show', requirements: ['id' => Requirement::UUID, 'wearer' => '[a-z]+'], methods: ['GET'])]
    public function show(string $id, string $wearer, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->loadout($this->owned->get($id, $player), $wearer));
    }

    #[Route('/api/characters/{id}/loadout/{wearer}/auto', name: 'loadout_auto', requirements: ['id' => Requirement::UUID, 'wearer' => '[a-z]+'], methods: ['POST'])]
    public function auto(string $id, string $wearer, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $this->inventory->autoEquip($character, $wearer);

        return new JsonResponse($this->views->loadout($character, $wearer));
    }
}
