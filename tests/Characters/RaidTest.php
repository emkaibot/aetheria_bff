<?php

declare(strict_types=1);

namespace App\Tests\Characters;

use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Guilds\Guild;
use App\Characters\Domain\Guilds\Raid;
use PHPUnit\Framework\TestCase;

final class RaidTest extends TestCase
{
    public function testTheWeekDecidesTheBoss(): void
    {
        self::assertSame('2026-W44', Raid::weekOf(new \DateTimeImmutable('2026-10-29')));
        self::assertSame(Boss::cases()[44 % 7], Raid::bossOf('2026-W44'));
    }

    public function testTheBossFallsOnceAndOnlyOnce(): void
    {
        $raid = new Raid('00000000-0000-0000-0000-000000000003', new Guild('00000000-0000-0000-0000-000000000004', 'Los del Éter'), '2026-W44', Boss::Lich, 1000);
        $now  = new \DateTimeImmutable();

        self::assertFalse($raid->hit(600, $now));
        self::assertTrue($raid->hit(600, $now));
        self::assertSame(0, $raid->hp());
        self::assertFalse($raid->hit(100, $now));
    }

    public function testGuildNamesCompareWithoutCaseOrAccents(): void
    {
        self::assertSame(Guild::key('Los del Éter'), Guild::key('los del eter'));
    }
}
