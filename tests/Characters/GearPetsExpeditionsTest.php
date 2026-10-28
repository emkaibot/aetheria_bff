<?php

declare(strict_types=1);

namespace App\Tests\Characters;

use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Combatant;
use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Expeditions\Difficulty;
use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Expeditions\Rates;
use App\Characters\Domain\Expeditions\Zone;
use App\Characters\Domain\Gear\Gear;
use App\Characters\Domain\Gear\GearForge;
use App\Characters\Domain\Gear\Loadout;
use App\Characters\Domain\Gear\Rarity;
use App\Characters\Domain\Gear\Slot;
use App\Characters\Domain\Hero;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Domain\Stats;
use PHPUnit\Framework\TestCase;

final class GearPetsExpeditionsTest extends TestCase
{
    private Character $character;

    protected function setUp(): void
    {
        $this->character = new Character('00000000-0000-0000-0000-000000000001', 'owner', 'Prueba', CharacterClass::Warrior);
    }

    public function testRarityDecidesHowManySecondariesAnItemHas(): void
    {
        $forge = new GearForge(new Rng(3));
        foreach (Rarity::cases() as $rarity) {
            $item = $forge->forge($this->character, Gear::AmethystRing, 10, $rarity);
            self::assertCount(count(Gear::AmethystRing->mains(10)) + $rarity->secondaries(), $item->attributes(), $rarity->value);
        }
    }

    public function testUpgradesRaiseEveryValue(): void
    {
        $item   = (new GearForge(new Rng(1)))->forge($this->character, Gear::EmberGreatsword, 10, Rarity::Epic);
        $before = $item->attributes()['atk'];
        $item->upgrade();

        self::assertGreaterThan($before, $item->attributes()['atk']);
        self::assertSame(Slot::Weapon, $item->getSlot());
    }

    public function testFirmamentOnlyDropsFromTheGolemOn(): void
    {
        $early = new GearForge(new Rng(5));
        for ($i = 0; $i < 300; $i++) {
            self::assertNotSame(Gear::FAMILY_FIRMAMENT, $early->drop($this->character, 5, 3)->getGear()->family());
        }
    }

    public function testWornGearAddsToTheStats(): void
    {
        $item   = (new GearForge(new Rng(2)))->forge($this->character, Gear::PaddedVest, 10, Rarity::Common);
        $plain  = Combatant::hero(Hero::Darius, 0);
        $geared = Combatant::hero(Hero::Darius, 0, Loadout::stats([$item]), Loadout::bonus([$item]));

        self::assertGreaterThan($plain->stats->hp, $geared->stats->hp);
    }

    public function testPetsEvolveAtTwentyAndForty(): void
    {
        self::assertSame('Azulín', Pet::Azurion->name(19));
        self::assertSame('Azuriel', Pet::Azurion->name(20));
        self::assertSame('Azurion', Pet::Azurion->name(40));
        self::assertSame(6, Pet::Azurion->bonus(45));
    }

    public function testPetsActInBattle(): void
    {
        $team   = [Combatant::leader('Líder', CharacterClass::Mage, 40)];
        // Un jefe que aguanta mucho y apenas pega: así se llega a las rondas de las mascotas.
        $result = (new BattleSimulator(new Rng(9)))->run($team, Boss::RatQueen, [[Pet::Azurion, 1], [Pet::Bastion, 1]], new Stats(5000, 1, 0, 1));

        $byPet = array_column(array_filter($result->log, fn ($e) => $e['actor'] === 'pet'), 'who');
        self::assertContains('azurion', $byPet);
        self::assertContains('bastion', $byPet);
        self::assertSame(['azurion', 'bastion'], array_column($result->pets, 'id'));
    }

    public function testExpeditionsStopCountingAtTheLimit(): void
    {
        $start = new \DateTimeImmutable('2026-10-28 00:00:00');
        $e     = new Expedition('00000000-0000-0000-0000-000000000002', $this->character, Zone::Ruins, Difficulty::Normal, 8, null, $start);

        self::assertSame(3600, $e->elapsedSeconds($start->modify('+1 hour')));
        self::assertSame(8 * 3600, $e->elapsedSeconds($start->modify('+20 hours')));
    }

    public function testAnUnderLevelledTeamEarnsLess(): void
    {
        $weak   = Rates::for(Zone::Crypt, Difficulty::Normal, 4);
        $strong = Rates::for(Zone::Crypt, Difficulty::Normal, 16);

        self::assertLessThan($strong->gold, $weak->gold);
        self::assertGreaterThan(Rates::for(Zone::Crypt, Difficulty::Normal, 16)->gold, Rates::for(Zone::Crypt, Difficulty::Nightmare, 24)->gold);
    }
}
