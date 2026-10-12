<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Security\Player;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/characters', name: 'characters_list', methods: ['GET'])]
final class ListCharactersController
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function __invoke(#[CurrentUser] Player $player): JsonResponse
    {
        $list    = $this->characters->listByOwner($player->getId());
        $records = $this->characters->battleRecords(array_map(fn (Character $c) => $c->getId(), $list));

        return new JsonResponse([
            'items' => array_map(fn (Character $c) => CharacterPresenter::character($c, $records[$c->getId()] ?? []), $list),
        ]);
    }
}
