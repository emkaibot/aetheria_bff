<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\FightBoss;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\InvalidCharacter;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Body: `{boss}`. Devuelve el combate entero (`log`) para que la app lo
 * reproduzca, el oro ganado (0 si pierde) y el héroe actualizado.
 */
#[Route('/api/characters/{id}/battles', name: 'battles_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class FightBossController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly FightBoss $fight,
        private readonly CharacterViews $views,
    ) {}

    public function __invoke(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $boss      = Boss::tryFrom(JsonBody::string(JsonBody::of($request), 'boss'))
            ?? throw new InvalidCharacter('Ese jefe no existe.');

        ['result' => $result, 'gold' => $gold] = ($this->fight)($character, $boss);

        return new JsonResponse([
            'won'         => $result->won,
            'rounds'      => $result->rounds,
            'hero_max_hp' => $result->heroMaxHp,
            'boss_max_hp' => $result->bossMaxHp,
            'log'         => $result->log,
            'boss'        => CharacterPresenter::boss($boss),
            'gold'        => $gold,
            'character'   => $this->views->one($character),
        ]);
    }
}
