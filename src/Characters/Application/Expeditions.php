<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Expeditions\Difficulty;
use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Expeditions\Rates;
use App\Characters\Domain\Expeditions\Zone;
use App\Characters\Domain\Gear\GearForge;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\InvalidExpedition;
use App\Characters\Domain\Progression;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Las expediciones AFK de un personaje: salir y recogerla. Lo que da se calcula al recoger, con el tiempo pasado (hasta el
 * límite) y el nivel del equipo en ese momento.
 */
final class Expeditions
{
    /** Objetos como mucho por expedición, para no llenar el inventario de golpe. */
    private const MAX_ITEMS = 40;

    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly Inventory $inventory,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Las zonas abiertas: las de los jefes que ya ha vencido. @return Zone[] */
    public function openZones(Character $c): array
    {
        $records = $this->characters->battleRecords([$c->getId()])[$c->getId()] ?? [];

        return array_values(array_filter(Zone::cases(), fn (Zone $z) => ($records[$z->boss()->value]['victories'] ?? 0) > 0));
    }

    public function start(Character $c, Zone $zone, Difficulty $difficulty, int $limitHours, ?Rarity $autosell): Expedition
    {
        return $this->em->wrapInTransaction(function () use ($c, $zone, $difficulty, $limitHours, $autosell): Expedition {
            $this->em->lock($c, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($c);

            if (!in_array($limitHours, Expedition::LIMITS, true)) {
                throw new InvalidExpedition('El límite tiene que ser de 8, 12 o 24 horas.');
            }
            if (!in_array($zone, $this->openZones($c), true)) {
                throw new InvalidExpedition(sprintf('Vence antes a %s para abrir %s.', $zone->boss()->name(), $zone->label()));
            }
            if ($this->characters->activeExpedition($c->getId()) !== null) {
                throw new InvalidExpedition('Tu equipo ya está de expedición: recógela antes de salir a otra.');
            }

            $expedition = new Expedition(Uuid::v7()->toRfc4122(), $c, $zone, $difficulty, $limitHours, $autosell);
            $this->em->persist($expedition);

            return $expedition;
        });
    }

    /**
     * Recoge la expedición en marcha y reparte el botín.
     *
     * @return array{expedition: Expedition, items: GearItem[]}
     */
    public function claim(Character $c, ?\DateTimeImmutable $now = null): array
    {
        return $this->em->wrapInTransaction(function () use ($c, $now): array {
            $this->em->lock($c, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($c);

            $expedition = $this->characters->activeExpedition($c->getId())
                ?? throw new InvalidExpedition('No tienes ninguna expedición en marcha.');
            $now ??= new \DateTimeImmutable();

            $seconds = $expedition->elapsedSeconds($now);
            $hours   = $seconds / 3600;
            $lineup  = Lineup::of($c, $this->characters);
            $rates   = Rates::for($expedition->getZone(), $expedition->getDifficulty(), Progression::teamLevel($c->getEnergy(), $lineup->party));

            $gold    = (int) floor($rates->gold * $hours);
            $exp     = (int) floor($rates->exp * $hours);
            $count   = min(self::MAX_ITEMS, (int) floor($rates->items * $hours));
            $level   = $rates->requiredLevel;
            // Semilla fija por expedición: recoger dos veces la misma (un
            // reintento) no podría dar otros objetos.
            $forge   = new GearForge(new Rng(crc32($expedition->getId())));
            $loot    = [];
            for ($i = 0; $i < $count; $i++) {
                $loot[] = $forge->drop($c, $level, $expedition->getZone()->tier());
            }

            $stored = $this->inventory->store($c, $loot, $expedition->getAutosell());
            $gained = Experience::grant($lineup->party, $lineup->pets, $exp);
            $c->earnGold($gold);

            $expedition->claim([
                'seconds'    => $seconds,
                'gold'       => $gold,
                'sold'       => $stored['sold'],
                'sold_gold'  => $stored['gold'],
                'exp'        => $exp,
                'enemies'    => (int) floor($rates->enemies * $hours),
                'bosses'     => (int) floor($rates->bosses * $hours),
                'items'      => array_map(fn (GearItem $i) => ['id' => $i->getId(), 'gear' => $i->getGear()->value, 'name' => $i->getGear()->label(), 'rarity' => $i->getRarity()->value, 'level' => $i->getLevel()], $stored['kept']),
                'legendary'  => array_filter($loot, fn (GearItem $i) => $i->getRarity()->atLeast(Rarity::Legendary)) !== [],
                'level_ups'  => $gained['level_ups'],
                'evolutions' => $gained['evolutions'],
                'heroes'     => array_map(fn ($h) => ['id' => $h->getHero()->value, 'name' => $h->getHero()->label(), 'level' => $h->level(), 'progress' => Progression::describe($h->getExp())['progress']], $lineup->party),
            ], $now);

            return ['expedition' => $expedition, 'items' => $stored['kept']];
        });
    }
}
