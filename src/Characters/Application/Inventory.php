<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\Role;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\Gear\Slot;
use App\Characters\Domain\Hero;
use App\Characters\Domain\InvalidGear;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * El inventario de un personaje: lo que cae, lo que se pone cada uno y lo que
 * se vende o se mejora. Todo con la fila del personaje bloqueada, para que
 * dos toques a la vez no gasten el mismo oro ni pongan dos cosas en la misma
 * ranura.
 */
final class Inventory
{
    /** Objetos como mucho; lo que no cabe se vende solo. */
    public const CAPACITY = 200;

    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Ponerle un objeto al líder (`leader`) o a un héroe. Lo que llevara en esa ranura se queda en el inventario. */
    public function equip(Character $c, GearItem $item, string $wearer): void
    {
        $this->locked($c, function () use ($c, $item, $wearer): void {
            $role = $this->roleOf($c, $wearer);
            if (!self::fits($item, $role)) {
                throw new InvalidGear(sprintf('%s sólo lo puede llevar un %s.', $item->getGear()->label(), mb_strtolower($item->getGear()->role()?->label() ?? '')));
            }
            $this->wear($c, $item, $wearer);
        });
    }

    public function unequip(Character $c, GearItem $item): void
    {
        $this->locked($c, fn () => $item->equipTo(null));
    }

    /** Lo mejor que haya libre para cada ranura (o lo que ya llevaba, si es mejor). */
    public function autoEquip(Character $c, string $wearer): void
    {
        $this->locked($c, function () use ($c, $wearer): void {
            $role = $this->roleOf($c, $wearer);
            $all  = $this->characters->gearFor([$c->getId()])[$c->getId()] ?? [];

            foreach (Slot::cases() as $slot) {
                $best = null;
                foreach ($all as $item) {
                    $free = $item->getEquippedBy() === null || $item->getEquippedBy() === $wearer;
                    if ($item->getSlot() === $slot && $free && self::fits($item, $role) && ($best === null || $item->power() > $best->power())) {
                        $best = $item;
                    }
                }
                if ($best !== null) {
                    $this->wear($c, $best, $wearer, $all);
                }
            }
        });
    }

    public function setLocked(Character $c, GearItem $item, bool $locked): void
    {
        $this->locked($c, fn () => $item->setLocked($locked));
    }

    /**
     * Vende objetos sueltos. Los bloqueados y los que lleva alguien puesto
     * no se venden. Devuelve el oro ganado.
     *
     * @param GearItem[] $items
     */
    public function sell(Character $c, array $items): int
    {
        return $this->locked($c, function () use ($c, $items): int {
            $gold = 0;
            foreach ($items as $item) {
                if ($item->isLocked() || $item->getEquippedBy() !== null) {
                    throw new InvalidGear(sprintf('%s está %s: no se puede vender.', $item->getGear()->label(), $item->isLocked() ? 'bloqueado' : 'equipado'));
                }
                $gold += $item->sellPrice();
                $this->em->remove($item);
            }
            $c->earnGold($gold);

            return $gold;
        });
    }

    public function upgrade(Character $c, GearItem $item): void
    {
        $this->locked($c, function () use ($c, $item): void {
            $cost = $item->upgradeCost() ?? throw new InvalidGear('Ya está mejorado al máximo.');
            $c->spendGold($cost);
            $item->upgrade();
        });
    }

    /**
     * Guarda el botín, dentro de la transacción de quien llama. Lo de la rareza
     * `$autosell` o menos se vende en el acto, y si no cabe en el inventario
     * se vende lo peor de lo nuevo.
     *
     * @param  GearItem[] $drops
     * @return array{kept: GearItem[], sold: int, gold: int}
     */
    public function store(Character $c, array $drops, ?Rarity $autosell = null): array
    {
        $owned = count($this->characters->gearFor([$c->getId()])[$c->getId()] ?? []);
        $kept  = [];
        $sell  = [];
        foreach ($drops as $item) {
            $autosell !== null && $autosell->atLeast($item->getRarity()) ? $sell[] = $item : $kept[] = $item;
        }

        usort($kept, fn (GearItem $a, GearItem $b) => $b->power() <=> $a->power());
        $room = max(0, self::CAPACITY - $owned);
        $sell = [...$sell, ...array_slice($kept, $room)];
        $kept = array_slice($kept, 0, $room);

        foreach ($kept as $item) {
            $this->em->persist($item);
        }
        $gold = array_sum(array_map(fn (GearItem $i) => $i->sellPrice(), $sell));
        $c->earnGold($gold);

        return ['kept' => $kept, 'sold' => count($sell), 'gold' => $gold];
    }

    /** El papel de quien se lo va a poner: el del líder sale de su clase. */
    public function roleOf(Character $c, string $wearer): Role
    {
        if ($wearer === 'leader') {
            return $c->getClass()->role();
        }

        $hero = Hero::tryFrom($wearer) ?? throw new InvalidGear('Ese héroe no existe.');
        foreach ($this->characters->heroesFor([$c->getId()])[$c->getId()] ?? [] as $h) {
            if ($h->getHero() === $hero) {
                return $hero->role();
            }
        }

        throw new InvalidGear(sprintf('%s no está en tu compañía.', $hero->label()));
    }

    public static function fits(GearItem $item, Role $role): bool
    {
        $only = $item->getGear()->role();

        return $only === null || $only === $role;
    }

    /** @param GearItem[]|null $all el inventario, si ya se tiene */
    private function wear(Character $c, GearItem $item, string $wearer, ?array $all = null): void
    {
        foreach ($all ?? $this->characters->gearFor([$c->getId()])[$c->getId()] ?? [] as $other) {
            if ($other !== $item && $other->getEquippedBy() === $wearer && $other->getSlot() === $item->getSlot()) {
                $other->equipTo(null);
            }
        }
        // El índice único (personaje, quien, ranura) se comprueba fila a fila:
        // lo que se quita tiene que llegar a la base antes que lo que se pone.
        $this->em->flush();
        $item->equipTo($wearer);
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
