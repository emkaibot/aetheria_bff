<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;

/**
 * La ficha de uno o varios héroes tal y como sale por la API: con su récord de
 * combates y su equipo, que viven en otras tablas. En una consulta por tabla
 * aunque sean muchos.
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

        return array_map(
            fn (Character $c) => CharacterPresenter::character($c, $records[$c->getId()] ?? [], $items[$c->getId()] ?? []),
            $list,
        );
    }
}
