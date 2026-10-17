<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Item;
use App\Characters\Domain\Perk;
use App\Characters\Domain\Progression;
use App\Characters\Infrastructure\Presenter\CharacterPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Las reglas del juego: clases, ventajas, jefes y la tienda. Público y sin estado; la
 * app lo pide al arrancar para pintar el selector de clase y la guía.
 */
#[Route('/public/catalog', name: 'catalog', methods: ['GET'])]
final class CatalogController
{
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse([
            'classes' => array_map(fn (CharacterClass $c) => CharacterPresenter::classFull($c), CharacterClass::cases()),
            'perks'   => array_map(fn (Perk $p) => CharacterPresenter::perk($p), Perk::cases()),
            'bosses'  => array_map(fn (Boss $b) => CharacterPresenter::boss($b), Boss::cases()),
            'items'   => array_map(fn (Item $i) => CharacterPresenter::item($i), Item::cases()),
            'rules'   => [
                'max_level'      => Progression::MAX_LEVEL,
                'name_min'       => Character::NAME_MIN,
                'name_max'       => Character::NAME_MAX,
            ],
        ]);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
