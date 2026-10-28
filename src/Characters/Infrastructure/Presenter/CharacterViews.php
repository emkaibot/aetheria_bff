<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Hero;

/**
 * La ficha de uno o varios personajes tal y como sale por la API: con su récord
 * de combates, su equipo y su compañía de héroes, que viven en otras tablas.
 * En una consulta por tabla aunque sean muchos.
 */
final class CharacterViews
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function one(Character $c): array
    {
        return $this->many([$c])[0];
    }

    /** @param Character[] $list */
    public function many(array $list): array
    {
        $ids     = array_map(fn (Character $c) => $c->getId(), $list);
        $records = $this->characters->battleRecords($ids);
        $items   = $this->characters->itemsFor($ids);
        $heroes  = $this->characters->heroesFor($ids);

        return array_map(
            fn (Character $c) => CharacterPresenter::character($c, $records[$c->getId()] ?? [], $items[$c->getId()] ?? [], $heroes[$c->getId()] ?? []),
            $list,
        );
    }

    /**
     * Los diez héroes vistos desde un personaje: los que tiene y los que le
     * faltan, en el orden del catálogo. Con su oro, para la pantalla de reclutar.
     */
    public function heroes(Character $c): array
    {
        $owned = [];
        foreach ($this->characters->heroesFor([$c->getId()])[$c->getId()] ?? [] as $h) {
            $owned[$h->getHero()->value] = $h;
        }

        return [
            'items'     => array_map(fn (Hero $h) => CharacterPresenter::heroFor($h, $owned[$h->value] ?? null), Hero::cases()),
            'party_max' => CharacterHero::PARTY_MAX,
            'gold'      => $c->getGold(),
        ];
    }
}
