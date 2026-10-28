<?php

declare(strict_types=1);

namespace App\Tests\Characters;

use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Benchmark;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Hero;
use PHPUnit\Framework\TestCase;

final class BattleSimulatorTest extends TestCase
{
    public function testSameSeedGivesSameFight(): void
    {
        $a = $this->fight(seed: 42);
        $b = $this->fight(seed: 42);

        self::assertSame(json_encode($a->log), json_encode($b->log));
    }

    public function testTheWholeTeamFightsAndIsListedLeaderFirst(): void
    {
        $result = $this->fight();

        self::assertSame(['leader', 'darius', 'seraphina', 'aelyn'], array_column($result->team, 'key'));
        self::assertSame('guardian', $result->team[1]['role']);

        $actors = array_unique(array_column(array_filter($result->log, fn ($e) => $e['actor'] === 'hero'), 'who'));
        foreach (['leader', 'darius', 'seraphina', 'aelyn'] as $key) {
            self::assertContains($key, $actors, "$key no llega a actuar");
        }
    }

    public function testEveryEntryCarriesTheHpOfTheWholeTeam(): void
    {
        foreach ($this->fight()->log as $entry) {
            self::assertSame(['leader', 'darius', 'seraphina', 'aelyn'], array_keys($entry['team_hp']));
            self::assertSame($entry['team_hp']['leader'], $entry['hero_hp']);
        }
    }

    public function testTheBossGrowsWithTheParty(): void
    {
        $solo  = (new BattleSimulator(new Rng(1)))->simulate('Solo', CharacterClass::Warrior, 0, Boss::Goblin);
        $party = $this->fight(boss: Boss::Goblin);

        self::assertSame(Boss::Goblin->stats()->hp, $solo->bossMaxHp);
        self::assertGreaterThan($solo->bossMaxHp, $party->bossMaxHp);
    }

    public function testTheFightEndsWithOneSideDownOrATimeout(): void
    {
        $result = $this->fight();
        $last   = $result->log[array_key_last($result->log)];

        $teamDown = array_sum($last['team_hp']) === 0;
        $bossDown = $last['boss_hp'] === 0;
        self::assertTrue($teamDown || $bossDown || $last['action'] === 'timeout');
        self::assertSame($bossDown && !$teamDown, $result->won);
    }

    public function testTheTypicalTeamTwoLevelsAboveTheRecommendedOneAlmostAlwaysWins(): void
    {
        foreach ([Boss::Skeleton, Boss::Lich] as $boss) {
            $level = $boss->recommendedLevel() + 2;
            $wins  = 0;
            for ($seed = 1; $seed <= 40; $seed++) {
                $wins += (new BattleSimulator(new Rng($seed)))->run(Benchmark::team(CharacterClass::Warrior, $boss, $level), $boss, Benchmark::pets($boss, $level))->won ? 1 : 0;
            }

            self::assertGreaterThanOrEqual(36, $wins, $boss->value);
        }
    }

    private function fight(int $seed = 7, Boss $boss = Boss::RatQueen): \App\Characters\Domain\Battle\BattleResult
    {
        return (new BattleSimulator(new Rng($seed)))->simulate(
            'Líder',
            CharacterClass::Mage,
            40,
            $boss,
            party: array_map(fn (Hero $h) => [$h, 40], Hero::STARTERS),
        );
    }
}
