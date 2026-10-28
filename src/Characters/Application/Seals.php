<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Gear\Gear;
use App\Characters\Domain\Gear\GearForge;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Hero;
use App\Characters\Domain\InvalidGear;
use App\Characters\Domain\InvalidParty;
use App\Characters\Domain\InvalidPets;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Domain\Progression;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * En qué se gastan los sellos de incursión: estrellas para los héroes, EXP
 * para las mascotas y el cofre primordial. Nunca éter: el éter sólo cambia
 * a mano.
 */
final class Seals
{
    /** Alimentar a una mascota: sellos y la EXP que le da. */
    public const FEED_COST = 10;
    public const FEED_EXP  = 300;
    /** Cofre primordial: una pieza del firmamento del nivel del equipo. */
    public const CHEST_COST = 80;

    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly Inventory $inventory,
        private readonly EntityManagerInterface $em,
    ) {}

    public function star(Character $c, Hero $hero): void
    {
        $this->locked($c, function () use ($c, $hero): void {
            foreach ($this->characters->heroesFor([$c->getId()])[$c->getId()] ?? [] as $h) {
                if ($h->getHero() === $hero) {
                    $cost = $h->nextStarCost() ?? throw new InvalidParty(sprintf('%s ya tiene todas las estrellas.', $hero->label()));
                    $c->spendSeals($cost);
                    $h->addStar();
                    return;
                }
            }
            throw new InvalidParty(sprintf('%s no está en tu compañía.', $hero->label()));
        });
    }

    /** @return int formas que ha cambiado (1 si evoluciona) */
    public function feed(Character $c, Pet $pet): int
    {
        return $this->locked($c, function () use ($c, $pet): int {
            foreach ($this->characters->petsFor([$c->getId()])[$c->getId()] ?? [] as $p) {
                if ($p->getPet() === $pet) {
                    $c->spendSeals(self::FEED_COST);
                    return $p->gainExp(self::FEED_EXP);
                }
            }
            throw new InvalidPets('Esa mascota no es tuya.');
        });
    }

    public function chest(Character $c): GearItem
    {
        return $this->locked($c, function () use ($c): GearItem {
            $c->spendSeals(self::CHEST_COST);
            $rng    = new Rng();
            $pieces = Gear::ofFamily(Gear::FAMILY_FIRMAMENT);
            $level  = Progression::teamLevel($c->getEnergy(), Lineup::of($c, $this->characters)->party);
            $item   = (new GearForge($rng))->forge($c, $pieces[(int) floor($rng->float() * count($pieces))], $level);
            $stored = $this->inventory->store($c, [$item]);
            if ($stored['kept'] === []) {
                throw new InvalidGear('El inventario está lleno: vende algo antes de abrir el cofre.');
            }

            return $item;
        });
    }

    /**
     * @template T
     * @param  callable(): T $work
     * @return T
     */
    private function locked(Character $c, callable $work): mixed
    {
        return $this->em->wrapInTransaction(function () use ($c, $work) {
            $this->em->lock($c, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($c);

            return $work();
        });
    }
}
