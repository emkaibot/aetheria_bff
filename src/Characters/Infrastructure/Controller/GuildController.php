<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Application\Guilds;
use App\Characters\Application\Raids;
use App\Characters\Domain\Guilds\GuildRepository;
use App\Characters\Domain\InvalidGuild;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use App\Characters\Infrastructure\Presenter\CharacterViews;
use App\Security\Player;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * El gremio de un personaje: verlo con su raid, crear uno (`{name}`), unirse
 * (`{guild}`), salir y atacar en la raid.
 */
final class GuildController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly GuildRepository $guildRepository,
        private readonly Guilds $guilds,
        private readonly Raids $raids,
        private readonly CharacterViews $views,
    ) {}

    #[Route('/api/characters/{id}/guild', name: 'guild_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        return new JsonResponse($this->view($id, $player));
    }

    #[Route('/api/characters/{id}/guild', name: 'guild_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function create(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $this->guilds->create($this->owned->get($id, $player), JsonBody::string(JsonBody::of($request), 'name'));

        return new JsonResponse($this->view($id, $player), Response::HTTP_CREATED);
    }

    #[Route('/api/characters/{id}/guild/join', name: 'guild_join', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function join(string $id, Request $request, #[CurrentUser] Player $player): JsonResponse
    {
        $guild = $this->guildRepository->find(JsonBody::string(JsonBody::of($request), 'guild'))
            ?? throw new InvalidGuild('Ese gremio ya no existe.');
        $this->guilds->join($this->owned->get($id, $player), $guild);

        return new JsonResponse($this->view($id, $player));
    }

    #[Route('/api/characters/{id}/guild/leave', name: 'guild_leave', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function leave(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $this->guilds->leave($this->owned->get($id, $player));

        return new JsonResponse($this->view($id, $player));
    }

    /**
     * Un ataque a la raid, con la misma forma que un combate contra un jefe
     * para que la app lo reproduzca igual. La vida del jefe en el registro es
     * la compartida del gremio: se ve bajar con cada golpe.
     */
    #[Route('/api/characters/{id}/raid/attack', name: 'raid_attack', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function attack(string $id, #[CurrentUser] Player $player): JsonResponse
    {
        $character = $this->owned->get($id, $player);
        $done      = $this->raids->attack($character);
        $result    = $done['result'];
        $before    = $done['hp_before'];
        $log       = array_map(
            fn (array $e) => ['boss_hp' => max(0, $before - ($result->bossMaxHp - $e['boss_hp']))] + $e,
            $result->log,
        );

        return new JsonResponse([
            'won'          => $done['killed'],
            'rounds'       => $result->rounds,
            'hero_max_hp'  => $result->heroMaxHp,
            'boss_max_hp'  => $done['raid']->getMaxHp(),
            'log'          => $log,
            'team'         => $result->team,
            'pets'         => $result->pets,
            'boss'         => CharacterPresenter::boss($done['raid']->getBoss()),
            'gold'         => 0,
            'exp'          => 0,
            'level_ups'    => new \stdClass(),
            'evolutions'   => new \stdClass(),
            'unlocked'     => null,
            'unlocked_pet' => null,
            'drops'        => [],
            'sold'         => 0,
            'raid'         => ['damage' => $done['damage'], 'seals' => $done['seals'], 'killed' => $done['killed'], 'hp' => $done['raid']->hp(), 'max_hp' => $done['raid']->getMaxHp()],
            'character'    => $this->views->one($character),
        ]);
    }

    private function view(string $id, Player $player): array
    {
        return $this->views->guild($this->owned->get($id, $player), $this->guildRepository, $this->raids);
    }
}
