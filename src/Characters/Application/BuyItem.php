<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterItem;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Item;
use App\Characters\Domain\ItemAlreadyOwned;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Comprar un objeto de la tienda para un héroe, pagando con su oro. */
final class BuyItem
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(Character $character, Item $item): void
    {
        $this->em->wrapInTransaction(function () use ($character, $item): void {
            // Bloqueada la fila: dos compras a la vez no gastan el mismo oro.
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            $owned = $this->characters->itemsFor([$character->getId()])[$character->getId()] ?? [];
            if (in_array($item, $owned, true)) {
                throw new ItemAlreadyOwned($item);
            }

            $character->spendGold($item->price());
            $this->em->persist(new CharacterItem(Uuid::v7()->toRfc4122(), $character, $item));
        });
    }
}
