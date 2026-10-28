<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\InvalidPets;
use App\Characters\Domain\Pets\CharacterPet;
use App\Characters\Domain\Pets\Pet;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Las mascotas de un personaje: las de serie, las que se liberan o se compran y cuáles van con el equipo. */
final class Kennel
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Las mascotas con las que nace un personaje, ya activas. Sin flush. */
    public function giveStarters(Character $character): void
    {
        foreach (Pet::STARTERS as $pet) {
            $this->em->persist(new CharacterPet(Uuid::v7()->toRfc4122(), $character, $pet, active: true));
        }
    }

    public function buy(Character $character, Pet $pet): void
    {
        $this->em->wrapInTransaction(function () use ($character, $pet): void {
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            if ($pet->price() === 0) {
                throw new InvalidPets(sprintf('%s no se compra: viene de serie.', $pet->name(1)));
            }
            $character->spendGold($pet->price());
            $this->add($character, $pet);
        });
    }

    /**
     * Da una mascota, dentro de la transacción de quien llama. Llega con la
     * EXP media de las que ya tiene y se activa si hay hueco.
     */
    public function add(Character $character, Pet $pet): CharacterPet
    {
        $owned = $this->characters->petsFor([$character->getId()])[$character->getId()] ?? [];
        foreach ($owned as $p) {
            if ($p->getPet() === $pet) {
                throw new InvalidPets(sprintf('Ya tienes a %s.', $p->getPet()->name($p->level())));
            }
        }

        $exp    = $owned === [] ? 0 : intdiv(array_sum(array_map(fn (CharacterPet $p) => $p->getExp(), $owned)), count($owned));
        $active = count(array_filter($owned, fn (CharacterPet $p) => $p->isActive())) < Pet::ACTIVE_MAX;
        $new    = new CharacterPet(Uuid::v7()->toRfc4122(), $character, $pet, $exp, $active);
        $this->em->persist($new);

        return $new;
    }

    /**
     * Cuáles van con el equipo: como mucho ACTIVE_MAX, de las que ya tiene.
     *
     * @param string[] $ids
     */
    public function setActive(Character $character, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) > Pet::ACTIVE_MAX) {
            throw new InvalidPets(sprintf('Van como mucho %d mascotas a la vez.', Pet::ACTIVE_MAX));
        }

        $owned = [];
        foreach ($this->characters->petsFor([$character->getId()])[$character->getId()] ?? [] as $p) {
            $owned[$p->getPet()->value] = $p;
        }
        foreach ($ids as $id) {
            if (!isset($owned[$id])) {
                throw new InvalidPets('Esa mascota no es tuya.');
            }
        }

        foreach ($owned as $id => $p) {
            $p->setActive(in_array($id, $ids, true));
        }
        $this->em->flush();
    }
}
