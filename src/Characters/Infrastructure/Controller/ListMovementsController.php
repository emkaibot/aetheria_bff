<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** El histórico de éter, el más reciente primero. `page` empieza en 1; `size` máx. 50. */
#[Route('/api/characters/{id}/movements', name: 'movements_list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ListMovementsController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $this->owned->get($id, $player);

        $page = max(1, $request->query->getInt('page', 1));
        $size = min(50, max(1, $request->query->getInt('size', 20)));

        $result = $this->characters->movements($id, $page, $size);

        return new JsonResponse([
            'items' => array_map(fn (EnergyMovement $m) => CharacterPresenter::movement($m), $result['items']),
            'total' => $result['total'],
            'page'  => $page,
            'size'  => $size,
        ]);
    }
}
