<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Los jefes tal y como los ve un personaje: cuáles puede retar y su récord con cada uno. */
#[Route('/api/characters/{id}/bosses', name: 'bosses_list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ListBossesController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $this->owned->get($id, $player);
        $records = $this->characters->battleRecords([$id])[$id] ?? [];

        $items = [];
        foreach (Boss::cases() as $boss) {
            $record   = $records[$boss->value] ?? null;
            $previous = $boss->previous();
            $status   = match (true) {
                ($record['victories'] ?? 0) > 0                                         => 'beaten',
                $previous === null || ($records[$previous->value]['victories'] ?? 0) > 0 => 'available',
                default                                                                 => 'locked',
            };
            $items[] = CharacterPresenter::boss($boss, $record, $status);
        }

        return new JsonResponse(['items' => $items]);
    }
}
