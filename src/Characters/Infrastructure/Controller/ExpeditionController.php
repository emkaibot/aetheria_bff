<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Expeditions;
use App\Characters\Domain\Expeditions\Difficulty;
use App\Characters\Domain\Expeditions\Zone;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\InvalidExpedition;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Characters\Infrastructure\Presenter\ExpeditionPresenter;
use App\Characters\Infrastructure\Presenter\GearPresenter;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * La expedición AFK de un personaje: su estado y las zonas, salir
 * (`{zone, difficulty, limit_hours, autosell}`) y recogerla.
 */
final class ExpeditionController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly Expeditions $expeditions,
        private readonly CharacterViews $views,
    ) {}

    #[Route('/api/characters/{id}/expedition', name: 'expedition_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->views->expedition($this->owned->get($id, $player), $this->expeditions));
    }

    #[Route('/api/characters/{id}/expedition', name: 'expedition_start', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function start(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $data      = JsonBody::of($request);
        $zone      = Zone::tryFrom(JsonBody::string($data, 'zone')) ?? throw new InvalidExpedition('Elige una zona.');
        $difficulty = Difficulty::tryFrom(JsonBody::string($data, 'difficulty')) ?? Difficulty::Normal;
        $limit     = is_int($data['limit_hours'] ?? null) ? $data['limit_hours'] : 12;
        $autosell  = Rarity::tryFrom(JsonBody::string($data, 'autosell'));

        $this->expeditions->start($character, $zone, $difficulty, $limit, $autosell);

        return new JsonResponse($this->views->expedition($character, $this->expeditions), Response::HTTP_CREATED);
    }

    #[Route('/api/characters/{id}/expedition/claim', name: 'expedition_claim', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function claim(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        ['expedition' => $expedition, 'items' => $items] = $this->expeditions->claim($character);

        return new JsonResponse([
            'claimed' => ExpeditionPresenter::expedition($expedition, new \DateTimeImmutable()),
            'items'   => array_map(fn ($i) => GearPresenter::item($i), $items),
        ] + $this->views->expedition($character, $this->expeditions));
    }
}
