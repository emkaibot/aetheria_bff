<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Hero;
use App\Characters\Domain\HeroAlreadyOwned;
use App\Characters\Domain\InvalidParty;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * La compañía de héroes de un personaje: los iniciales, los que se reclutan
 * con oro o se liberan venciendo a un jefe, y quiénes van en el equipo.
 */
final class Roster
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Los héroes con los que nace un personaje, ya en su equipo. Sin flush. */
    public function giveStarters(Character $character): void
    {
        foreach (Hero::STARTERS as $hero) {
            $this->em->persist(new CharacterHero(Uuid::v7()->toRfc4122(), $character, $hero, inParty: true));
        }
    }

    /** Reclutar a un héroe pagando su precio en oro, sin esperar a vencer a su jefe. */
    public function recruit(Character $character, Hero $hero): void
    {
        $this->em->wrapInTransaction(function () use ($character, $hero): void {
            // Bloqueada la fila: dos compras a la vez no gastan el mismo oro.
            $this->em->lock($character, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($character);

            if ($hero->price() === 0) {
                throw new InvalidParty(sprintf('%s no se recluta: viene de serie.', $hero->label()));
            }
            $character->spendGold($hero->price());
            $this->add($character, $hero);
        });
    }

    /**
     * Da un héroe a un personaje, dentro de la transacción de quien llama.
     *
     * Llega con la EXP media de los que ya tiene, para que el que se libera
     * tarde no sea un lastre de nivel 1, y entra en el equipo si hay sitio.
     */
    public function add(Character $character, Hero $hero): CharacterHero
    {
        $owned = $this->characters->heroesFor([$character->getId()])[$character->getId()] ?? [];
        foreach ($owned as $h) {
            if ($h->getHero() === $hero) {
                throw new HeroAlreadyOwned($hero);
            }
        }

        $exp     = $owned === [] ? 0 : intdiv(array_sum(array_map(fn (CharacterHero $h) => $h->getExp(), $owned)), count($owned));
        $inParty = count(array_filter($owned, fn (CharacterHero $h) => $h->isInParty())) < CharacterHero::PARTY_MAX;
        $new     = new CharacterHero(Uuid::v7()->toRfc4122(), $character, $hero, $exp, $inParty);
        $this->em->persist($new);

        return $new;
    }

    /**
     * Quiénes van en el equipo. Tienen que ser héroes que ya tiene, como mucho
     * PARTY_MAX; puede ir vacío y entonces el líder pelea solo.
     *
     * @param string[] $ids
     */
    public function setParty(Character $character, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) > CharacterHero::PARTY_MAX) {
            throw new InvalidParty(sprintf('En el equipo caben %d héroes.', CharacterHero::PARTY_MAX));
        }

        $owned = [];
        foreach ($this->characters->heroesFor([$character->getId()])[$character->getId()] ?? [] as $h) {
            $owned[$h->getHero()->value] = $h;
        }
        foreach ($ids as $id) {
            if (!isset($owned[$id])) {
                throw new InvalidParty('Ese héroe no está en tu compañía.');
            }
        }

        foreach ($owned as $id => $h) {
            $h->setInParty(in_array($id, $ids, true));
        }
        $this->em->flush();
    }
}
